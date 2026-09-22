<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

final class PublishNoteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'markdown' => ['required', 'string', 'max:2000000'],
            'assets' => ['array', 'max:200'],
            'assets.*' => [
                'file',
                'mimes:png,jpg,jpeg,gif,webp',
                'max:20480',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value instanceof UploadedFile && ! preg_match('/^[A-Za-z0-9._-]{1,120}$/', $value->getClientOriginalName())) {
                        $fail('The :attribute file name may only contain letters, numbers, dots, dashes and underscores.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $names = array_map(fn (UploadedFile $file): string => $file->getClientOriginalName(), $this->assetFiles());

                if (count($names) !== count(array_unique($names))) {
                    $validator->errors()->add('assets', 'Asset file names must be unique.');
                }
            },
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function assetFiles(): array
    {
        $files = $this->file('assets', []);

        return array_values(array_filter(is_array($files) ? $files : [], fn (mixed $file): bool => $file instanceof UploadedFile));
    }
}
