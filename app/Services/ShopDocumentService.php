<?php

namespace App\Services;

use App\Models\MechanicDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ShopDocumentService
{
    // DOCUMENT VALIDATION

    public function rules(bool $registration = false): array
    {
        $rules = [];

        foreach (MechanicDocument::TYPES as $type => $label) {

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
        }

        // Optional expiration date for Valid ID

        $rules['valid_id_expiration'] = [
            'nullable',
            'date_format:Y-m-d',
            'after_or_equal:today',
        ];

        // Required Business Permit expiration

        $rules['business_permit_expiration'] = [
            'required',
            'date_format:Y-m-d',
            'after_or_equal:today',
        ];

        // Required Mayor's Permit expiration

        $rules['mayors_permit_expiration'] = [
            'required',
            'date_format:Y-m-d',
            'after_or_equal:today',
        ];

        // Exclude document requirements for Rider accounts

        if ($registration) {

            foreach ([
                'valid_id_expiration',
                'business_permit_expiration',
                'mayors_permit_expiration',
            ] as $field) {

                array_unshift(
                    $rules[$field],
                    'exclude_unless:role,mechanic'
                );
            }
        }

        return $rules;
    }


    // STORE UPLOADED DOCUMENTS PRIVATELY

    public function store(Request $request): array
    {
        $documents = [];

        try {

            foreach (MechanicDocument::TYPES as $type => $label) {

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

                $documents[] = [

                    'document_type' => $type,

                    'document_path' => $path,

                    'original_filename' => mb_substr(
                        $file->getClientOriginalName(),
                        0,
                        255
                    ),

                    // BIR Certificate has no expiration field

                    'expiration_date' => $type === 'bir_certificate'
                        ? null
                        : ($request->input($type.'_expiration') ?: null),

                    'status' => 'pending',

                    'rejection_reason' => null,

                    'reviewed_by' => null,

                    'reviewed_at' => null,

                ];
            }

            return $documents;

        } catch (Throwable $exception) {

            // Remove uploaded files if an error occurs

            $this->delete(
                array_column(
                    $documents,
                    'document_path'
                )
            );

            throw $exception;
        }
    }


    // DELETE REPLACED OR FAILED DOCUMENT UPLOADS

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
