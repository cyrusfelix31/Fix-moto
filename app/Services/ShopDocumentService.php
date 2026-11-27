<?php

namespace App\Services;

use App\Models\MechanicDocument;
use App\Models\MechanicProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ShopDocumentService
{
    public const EXPIRATION_REQUIRED = [
        'business_permit',
        'mayors_permit',
    ];

    /*
    |--------------------------------------------------------------------------
    | DOCUMENT VALIDATION
    |--------------------------------------------------------------------------
    */

    public function rules(
        bool $registration = false,
        ?array $types = null
    ): array {
        $rules = [];

        $types ??= array_keys(
            MechanicDocument::TYPES
        );

        foreach ($types as $type) {

            if (!array_key_exists(
                $type,
                MechanicDocument::TYPES
            )) {
                continue;
            }

            $fileRules = [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ];

            if ($registration) {
                array_unshift(
                    $fileRules,
                    'exclude_unless:role,mechanic'
                );
            }

            $rules[$type] = $fileRules;

            if ($type === 'bir_certificate') {
                continue;
            }

            $expirationRules = [
                in_array(
                    $type,
                    self::EXPIRATION_REQUIRED,
                    true
                ) ? 'required' : 'nullable',

                'date_format:Y-m-d',

                'after_or_equal:today',
            ];

            if ($registration) {
                array_unshift(
                    $expirationRules,
                    'exclude_unless:role,mechanic'
                );
            }

            $rules[$type.'_expiration'] =
                $expirationRules;
        }

        return $rules;
    }

    /*
    |--------------------------------------------------------------------------
    | DOCUMENTS THAT REQUIRE REPLACEMENT
    |--------------------------------------------------------------------------
    */

    public function replacementTypes(
        MechanicProfile $profile
    ): array {
        $documents = $profile->documents()
            ->get()
            ->keyBy('document_type');

        $required = [];

        foreach (
            MechanicDocument::TYPES as $type => $label
        ) {

            $document = $documents->get($type);

            if (!$document) {
                $required[] = $type;
                continue;
            }

            if (
                $document->status === 'rejected'
                || $document->isExpired()
            ) {
                $required[] = $type;
                continue;
            }

            if (
                in_array(
                    $type,
                    self::EXPIRATION_REQUIRED,
                    true
                )
                && !$document->expiration_date
            ) {
                $required[] = $type;
                continue;
            }

            if (
                !$document->document_path
                || !Storage::disk('local')->exists(
                    $document->document_path
                )
            ) {
                $required[] = $type;
            }
        }

        return $required;
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE DOCUMENT STORAGE
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request,
        ?array $types = null
    ): array {
        $documents = [];
        $uploadedPaths = [];

        $types ??= array_keys(
            MechanicDocument::TYPES
        );

        try {

            foreach ($types as $type) {

                if (!array_key_exists(
                    $type,
                    MechanicDocument::TYPES
                )) {
                    continue;
                }

                $file = $request->file($type);

                if (!$file || !$file->isValid()) {
                    throw new RuntimeException(
                        'A required document is missing or invalid.'
                    );
                }

                $path = $file->store(
                    'mechanic-documents',
                    'local'
                );

                if (!$path) {
                    throw new RuntimeException(
                        'Document upload failed.'
                    );
                }

                $uploadedPaths[] = $path;

                $documents[] = [

                    'document_type' => $type,

                    'document_path' => $path,

                    'original_filename' => mb_substr(
                        $file->getClientOriginalName(),
                        0,
                        255
                    ),

                    'expiration_date' =>
                        $type === 'bir_certificate'
                            ? null
                            : (
                                $request->input(
                                    $type.'_expiration'
                                ) ?: null
                            ),

                    'status' => 'pending',

                    'rejection_reason' => null,

                    'reviewed_by' => null,

                    'reviewed_at' => null,

                ];
            }

            return $documents;

        } catch (Throwable $exception) {

            $this->delete($uploadedPaths);

            throw $exception;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE OLD DOCUMENT FILES
    |--------------------------------------------------------------------------
    */

    public function delete(array $paths): void
    {
        foreach ($paths as $path) {

            if (
                is_string($path)
                && str_starts_with(
                    $path,
                    'mechanic-documents/'
                )
            ) {

                Storage::disk('local')->delete($path);

            }
        }
    }
}