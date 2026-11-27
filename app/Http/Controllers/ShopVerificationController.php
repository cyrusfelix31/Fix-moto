<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MechanicDocument;
use App\Models\User;
use App\Services\ShopDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            && $profile->verification_status === 'approved'
        ) {
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

        $canResubmit =

            $profile->verification_status === 'rejected'

            || (

                $profile->verification_status === 'pending'

                && $profile->documents->isEmpty()

            );

        return view(
            'auth.pending-verification',
            compact(
                'profile',
                'latestVerification',
                'replacementTypes',
                'canResubmit'
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

            'pending' => $shops->filter(
                fn ($shop) =>
                    $shop->mechanicProfile
                        ->verification_status === 'pending'
            )->count(),

            'approved' => $shops->filter(
                fn ($shop) =>
                    $shop->mechanicProfile
                        ->verification_status === 'approved'
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

        $latestVerification = $user
            ->mechanicVerifications()
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->first();

        $rejectionReasons =
            self::REJECTION_REASONS;

        return view(
            'admin.verification-details',
            compact(
                'user',
                'profile',
                'latestVerification',
                'rejectionReasons'
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

        DB::transaction(function () use (

            $request,

            $document,

            $data

        ) {

            $profile = $document
                ->mechanicProfile()

                ->lockForUpdate()

                ->firstOrFail();

            abort_unless(
                $profile->verification_status === 'pending',
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

        DB::transaction(function () use (

            $request,

            $user,

            $data

        ) {

            $profile = $user
                ->mechanicProfile()

                ->lockForUpdate()

                ->firstOrFail();

            abort_unless(
                $profile->verification_status === 'pending',
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
                        ? 'approved'
                        : 'rejected',

                'is_verified' => $approved,

                'is_online' => false,

            ]);

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

                $eligible =

                    $lockedProfile->verification_status === 'rejected'

                    || (

                        $lockedProfile->verification_status === 'pending'

                        && !$lockedProfile->documents()->exists()

                    );

                abort_unless(
                    $eligible,
                    409,
                    'This application cannot be resubmitted.'
                );

                $types =
                    $documentService->replacementTypes(
                        $lockedProfile
                    );

                $rules = $documentService->rules(
                    false,
                    $types
                );

                $rules['correction_note'] = [

                    empty($types)
                        ? 'required'
                        : 'nullable',

                    'string',

                    'max:1000',

                ];

                $data = $request->validate(
                    $rules
                );

                $documents = $documentService->store(
                    $request,
                    $types
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

                $lockedProfile->update([

                    'verification_status' => 'pending',

                    'is_verified' => false,

                    'is_online' => false,

                ]);

                AuditLog::record(

                    $user,

                    'mechanic.documents_resubmitted',

                    details: [

                        'replaced_documents' =>
                            implode(', ', $types),

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
}