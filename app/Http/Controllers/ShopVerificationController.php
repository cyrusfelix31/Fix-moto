<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MechanicDocument;
use App\Models\User;
use App\Mail\MechanicAccountSetupMail;
use App\Notifications\MechanicVerificationUpdated;
use App\Notifications\MechanicDocumentResubmitted;
use App\Services\ShopDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ShopVerificationController extends Controller
{
    public const REJECTION_REASONS = [

        'Expired document',

        'Blurry or unreadable document',

        'Incomplete document',

        'Incorrect document',

        'Information does not match',

        'Invalid document',

        'Other reason',

    ];

    /*
    |--------------------------------------------------------------------------
    | MECHANIC VERIFICATION STATUS
    |--------------------------------------------------------------------------
    */

    public function status(
        Request $request,
        ShopDocumentService $documentService
    ) {
        $user = $request->user();

        abort_unless(
            $user && $user->role === 'mechanic',
            403
        );

        abort_if($user->is_suspended, 403);

        $profile = $user->mechanicProfile()
            ->with('documents')
            ->firstOrFail();

        if (
            $profile->is_verified
            && $profile->verification_status === 'fully_verified'
        ) {
            if ($user->password_setup_required) {
                return redirect()->route('mechanic.password.setup.notice');
            }
            return redirect()->route(
                'mechanic.dashboard'
            );
        }

        $latestVerification = $user
            ->mechanicVerifications()
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->first();

        $replacementTypes =
            $documentService->replacementTypes(
                $profile
            );
        if ($profile->verification_status === 'rejected') {
            $replacementTypes = array_values(array_intersect(
                $replacementTypes,
                $profile->documents->where('status', 'rejected')->pluck('document_type')->all()
            ));
        }

        $canResubmit = (in_array($profile->verification_status, [
            'partially_verified', 'under_review', 'pending_verification',
        ], true) && ($replacementTypes !== [] || $profile->documents->isEmpty()))
            || ($profile->verification_status === 'rejected'
                && $profile->documents->contains('status', 'rejected'));
        $progress = $profile->verificationProgress();

        return view(
            'auth.pending-verification',
            compact(
                'profile',
                'latestVerification',
                'replacementTypes',
                'canResubmit',
                'progress'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN VERIFICATION LIST
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $shops = User::query()

            ->where('role', 'mechanic')

            ->whereHas('mechanicProfile')

            ->with('mechanicProfile')

            ->latest()

            ->get();

        $counts = [

            'pending' => $shops->filter(fn ($shop) => in_array(
                $shop->mechanicProfile->verification_status,
                ['pending_verification', 'under_review', 'partially_verified'],
                true
            ))->count(),

            'approved' => $shops->filter(
                fn ($shop) =>
                    $shop->mechanicProfile
                        ->verification_status === 'fully_verified'
            )->count(),

            'rejected' => $shops->filter(
                fn ($shop) =>
                    $shop->mechanicProfile
                        ->verification_status === 'rejected'
            )->count(),

        ];

        return view(
            'admin.verification',
            compact('shops', 'counts')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DISPLAY SHOP APPLICATION
    |--------------------------------------------------------------------------
    */

    public function show(
        Request $request,
        User $user
    ) {
        $this->authorizeAdmin($request);

        abort_unless(
            $user->role === 'mechanic',
            404
        );

        $profile = $user->mechanicProfile()

            ->with([
                'documents',
                'members'
            ])

            ->firstOrFail();

        if ($profile->verification_status === 'pending_verification') {
            $profile->update(['verification_status' => 'under_review']);
            $user->notify(new MechanicVerificationUpdated(
                'under_review',
                'An administrator has started reviewing your shop documents.'
            ));
        }

        $latestVerification = $user
            ->mechanicVerifications()
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->first();

        $rejectionReasons =
            self::REJECTION_REASONS;
        $progress = $profile->verificationProgress();

        return view(
            'admin.verification-details',
            compact(
                'user',
                'profile',
                'latestVerification',
                'rejectionReasons',
                'progress'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SECURE DOCUMENT PREVIEW
    |--------------------------------------------------------------------------
    */

    public function viewDocument(
        Request $request,
        MechanicDocument $document
    ) {
        $this->authorizeAdmin($request);

        $disk = Storage::disk('local');

        abort_unless(
            $document->document_path

            && $disk->exists(
                $document->document_path
            ),
            404
        );

        $mimeType = $disk->mimeType(
            $document->document_path
        );

        abort_unless(

            in_array($mimeType, [

                'application/pdf',

                'image/jpeg',

                'image/png',

            ], true),

            415

        );

        return response()->file(

            $disk->path(
                $document->document_path
            ),

            [

                'Content-Type' => $mimeType,

                'Content-Disposition' => 'inline',

                'X-Content-Type-Options' => 'nosniff',

                'Cache-Control' => 'private, no-store',

            ]

        );
    }

    /*
    |--------------------------------------------------------------------------
    | REVIEW INDIVIDUAL DOCUMENT
    |--------------------------------------------------------------------------
    */

    public function reviewDocument(
        Request $request,
        MechanicDocument $document
    ) {
        $this->authorizeAdmin($request);

        $data = $this->validateDecision(
            $request
        );

        $setupLinkNeeded = false;
        DB::transaction(function () use (

            $request,

            $document,

            $data,
            &$setupLinkNeeded

        ) {

            $profile = $document
                ->mechanicProfile()

                ->lockForUpdate()

                ->firstOrFail();

            abort_unless(
                in_array($profile->verification_status, ['under_review', 'partially_verified', 'pending_verification'], true),
                409,
                'This application is not pending.'
            );

            $document->refresh();

            abort_unless(
                (int) $document->mechanic_profile_id
                    === (int) $profile->id,
                404
            );

            $approved =
                $data['decision'] === 'approve';

            if ($approved) {

                if (
                    $document->isExpired()

                    || (

                        in_array(
                            $document->document_type,
                            ShopDocumentService::EXPIRATION_REQUIRED,
                            true
                        )

                        && !$document->expiration_date

                    )
                ) {

                    throw ValidationException::withMessages([

                        'document' =>
                            'An expired document or a permit without an expiration date cannot be approved.',

                    ]);
                }

                if (
                    !$document->document_path

                    || !Storage::disk('local')->exists(
                        $document->document_path
                    )
                ) {

                    throw ValidationException::withMessages([

                        'document' =>
                            'The document file is missing.',

                    ]);
                }
            }

            $document->update([

                'status' =>
                    $approved
                        ? 'approved'
                        : 'rejected',

                'rejection_reason' =>
                    $approved
                        ? null
                        : $data['reason'],

                'reviewed_by' =>
                    $request->user()->id,

                'reviewed_at' => now(),

            ]);

            $status = $profile->refreshVerificationStatus();
            $setupLinkNeeded = $status === 'fully_verified'
                && $profile->mechanic->password_setup_required;
            $profile->mechanic->notify(new MechanicVerificationUpdated(
                $status,
                $approved
                    ? $document->label.' was approved.'
                    : $document->label.' was rejected. Please review the reason and upload a replacement.',
                $document->document_type,
                $approved ? null : $data['reason'],
            ));

            AuditLog::record(

                $request->user(),

                'mechanic.document_reviewed',

                $profile->mechanic,

                [

                    'document_type' =>
                        $document->document_type,

                    'decision' =>
                        $data['decision'],

                    'reason' =>
                        $approved
                            ? null
                            : $data['reason'],

                ]

            );

        });

        if ($setupLinkNeeded) {
            $this->sendPasswordSetupLink($document->mechanicProfile->mechanic);
        }

        return back()->with(

            'success',

            $data['decision'] === 'approve'

                ? 'Document approved successfully.'

                : 'Document rejected. The reason has been recorded.'

        );
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE OR REJECT ENTIRE APPLICATION
    |--------------------------------------------------------------------------
    */

    public function decide(
        Request $request,
        User $user
    ) {
        $this->authorizeAdmin($request);

        abort_unless(
            $user->role === 'mechanic',
            404
        );

        $data = $this->validateDecision(
            $request
        );

        $setupLinkNeeded = false;
        DB::transaction(function () use (

            $request,

            $user,

            $data,
            &$setupLinkNeeded

        ) {

            $profile = $user
                ->mechanicProfile()

                ->lockForUpdate()

                ->firstOrFail();

            abort_unless(
                in_array($profile->verification_status, ['under_review', 'partially_verified', 'pending_verification'], true),
                409,
                'This application has already been reviewed.'
            );

            $approved =
                $data['decision'] === 'approve';

            if ($approved) {

                if ($user->is_suspended) {

                    throw ValidationException::withMessages([

                        'verification' =>
                            'A suspended account cannot be approved.',

                    ]);
                }

                if (!$profile->allDocumentsApproved()) {

                    throw ValidationException::withMessages([

                        'verification' =>
                            'All required documents must be approved and valid.',

                    ]);
                }

                foreach (
                    $profile->documents as $document
                ) {

                    if (

                        !$document->document_path

                        || !Storage::disk('local')->exists(
                            $document->document_path
                        )

                    ) {

                        throw ValidationException::withMessages([

                            'verification' =>
                                'A required document file is missing.',

                        ]);
                    }
                }
            }

            $profile->update([

                'verification_status' =>
                    $approved
                        ? 'fully_verified'
                        : 'rejected',

                'is_verified' => $approved,

                'is_online' => false,

            ]);
            $setupLinkNeeded = $approved && $user->password_setup_required;

            $user->mechanicVerifications()->create([

                'document_path' => '',

                'status' =>
                    $approved
                        ? 'approved'
                        : 'rejected',

                'rejection_reason' =>
                    $approved
                        ? null
                        : $data['reason'],

                'reviewed_by' =>
                    $request->user()->id,

                'reviewed_at' => now(),

            ]);

            $user->notify(new MechanicVerificationUpdated(
                $approved ? 'fully_verified' : 'rejected',
                $approved
                    ? 'Your shop verification is complete. You can now go online and accept service requests.'
                    : 'Your application was denied. Review the administrator feedback and rejected document reasons.',
                reason: $approved ? null : $data['reason'],
            ));

            AuditLog::record(

                $request->user(),

                'mechanic.verification_updated',

                $user,

                [

                    'decision' =>
                        $data['decision'],

                    'reason' =>
                        $approved
                            ? null
                            : $data['reason'],

                ]

            );

        });

        if ($setupLinkNeeded) {
            $this->sendPasswordSetupLink($user);
        }

        return redirect()

            ->route('admin.verification')

            ->with(

                'success',

                $data['decision'] === 'approve'

                    ? 'Mechanic shop approved successfully.'

                    : 'Application rejected. The shop owner can now review the rejection reasons.'

            );
    }

    /*
    |--------------------------------------------------------------------------
    | SELECTIVE DOCUMENT RESUBMISSION
    |--------------------------------------------------------------------------
    */

    public function resubmit(
        Request $request,
        ShopDocumentService $documentService
    ) {
        $user = $request->user();

        abort_unless(
            $user && $user->role === 'mechanic',
            403
        );

        abort_if($user->is_suspended, 403);

        $profile = $user
            ->mechanicProfile()
            ->firstOrFail();

        $documents = [];
        $oldPaths = [];

        try {

            DB::transaction(function () use (

                $request,

                $profile,

                $user,

                $documentService,

                &$documents,

                &$oldPaths

            ) {

                $lockedProfile = $profile
                    ->newQuery()

                    ->whereKey($profile->id)

                    ->lockForUpdate()

                    ->firstOrFail();

                $eligible = in_array($lockedProfile->verification_status, [
                    'under_review', 'partially_verified', 'pending_verification',
                ], true) || $lockedProfile->verification_status === 'rejected';

                abort_unless(
                    $eligible,
                    409,
                    'This application cannot be resubmitted.'
                );

                if ($lockedProfile->verification_status === 'rejected') {
                    abort_unless(
                        $lockedProfile->documents()->where('status', 'rejected')->exists(),
                        409,
                        'Only rejected documents can be resubmitted.'
                    );
                }

                $types =
                    $documentService->replacementTypes(
                        $lockedProfile
                    );

                if ($lockedProfile->verification_status === 'rejected') {
                    $types = array_values(array_intersect(
                        $types,
                        $lockedProfile->documents()
                            ->where('status', 'rejected')
                            ->pluck('document_type')
                            ->all()
                    ));
                }

                abort_unless($types !== [], 409, 'There are no rejected or missing documents to replace.');

                $submittedTypes = array_values(array_filter(
                    $types,
                    fn (string $type): bool => $request->hasFile($type)
                ));
                abort_unless($submittedTypes !== [], 422, 'Choose at least one rejected document to re-upload.');

                $rules = $documentService->rules(
                    false,
                    $submittedTypes
                );
                $rules['correction_note'] = ['nullable', 'string', 'max:1000'];

                $data = $request->validate(
                    $rules
                );

                $documents = $documentService->store(
                    $request,
                    $submittedTypes
                );

                foreach (
                    $documents as $newDocument
                ) {

                    $existing = $lockedProfile
                        ->documents()

                        ->where(
                            'document_type',
                            $newDocument['document_type']
                        )

                        ->first();

                    if ($existing) {

                        if ($existing->document_path) {

                            $oldPaths[] =
                                $existing->document_path;
                        }

                        $existing->update(
                            $newDocument
                        );

                    } else {

                        $lockedProfile
                            ->documents()

                            ->create(
                                $newDocument
                            );
                    }

                }

                // Approved documents that were not
                // replaced retain their existing status.

                $approvedDocumentsRemain = $lockedProfile->documents()
                    ->where('status', 'approved')
                    ->whereNotIn('document_type', $submittedTypes)
                    ->exists();

                $newStatus = $approvedDocumentsRemain ? 'partially_verified' : 'pending_verification';
                $lockedProfile->update([
                    'verification_status' => $newStatus,
                    'is_verified' => false,
                    'is_online' => false,
                ]);
                $user->notify(new MechanicVerificationUpdated(
                    $newStatus,
                    'Your replacement documents were submitted and are waiting for review.'
                ));
                User::query()->where('role', 'admin')->get()->each(
                    fn (User $admin) => $admin->notify(new MechanicDocumentResubmitted(
                        $user->id,
                        $lockedProfile->shop_name,
                        array_map(fn (string $type): string => MechanicDocument::TYPES[$type] ?? $type, $submittedTypes),
                    ))
                );

                AuditLog::record(

                    $user,

                    'mechanic.documents_resubmitted',

                    details: [

                        'replaced_documents' =>
                            implode(', ', $submittedTypes),

                        'correction_note' =>
                            $data['correction_note'] ?? null,

                    ]

                );

            });

        } catch (Throwable $exception) {

            $documentService->delete(
                array_column(
                    $documents,
                    'document_path'
                )
            );

            throw $exception;
        }

        // Remove old files only after a successful commit.

        $documentService->delete(
            $oldPaths
        );

        return redirect()

            ->route('mechanic.verification.status')

            ->with(

                'success',

                'Your application has been resubmitted for verification.'

            );
    }

    /*
    |--------------------------------------------------------------------------
    | REJECTION REASON VALIDATION
    |--------------------------------------------------------------------------
    */

    private function validateDecision(
        Request $request
    ): array {

        $data = $request->validate([

            'decision' => [

                'required',

                'in:approve,reject',

            ],

            'reason_category' => [

                'nullable',

                'string',

                Rule::in(
                    self::REJECTION_REASONS
                ),

            ],

            'explanation' => [

                'nullable',

                'string',

                'max:850',

            ],

            // Compatibility with existing rejection forms.

            'reason' => [

                'nullable',

                'string',

                'max:1000',

            ],

        ]);

        if ($data['decision'] === 'approve') {

            $data['reason'] = null;

            return $data;
        }

        $category = trim(
            $data['reason_category'] ?? ''
        );

        $explanation = trim(
            $data['explanation'] ?? ''
        );

        $legacyReason = trim(
            $data['reason'] ?? ''
        );

        if ($category !== '') {

            if ($explanation === '') {

                throw ValidationException::withMessages([

                    'explanation' =>
                        'Please explain what the shop owner needs to correct.',

                ]);
            }

            $data['reason'] =
                $category . "\n" . $explanation;

        } elseif ($legacyReason !== '') {

            $data['reason'] = $legacyReason;

        } else {

            throw ValidationException::withMessages([

                'reason_category' =>
                    'Please provide a rejection reason.',

            ]);
        }

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN AUTHORIZATION
    |--------------------------------------------------------------------------
    */

    private function authorizeAdmin(
        Request $request
    ): void {

        abort_unless(

            $request->user()?->role === 'admin'

            && !$request->user()->is_suspended,

            403

        );
    }

    private function sendPasswordSetupLink(User $user): void
    {
        if (!$user->password_setup_required || !$user->mechanicProfile?->is_verified) {
            return;
        }

        try {
            $token = Password::broker()->createToken($user);
            Mail::to($user->email)->send(new MechanicAccountSetupMail($user, $token));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
