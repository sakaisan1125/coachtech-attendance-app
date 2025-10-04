<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Carbon\Carbon;

class CorrectionRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $time = ['nullable', 'regex:/^(2[0-3]|[01]\d):[0-5]\d$/'];
        return [
            'clock_in_at'       => $time,
            'clock_out_at'      => $time,
            'breaks'            => ['array'],
            'breaks.*.start'    => $time,
            'breaks.*.end'      => $time,
            'notes'             => ['required', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'clock_in_at'        => '出勤時刻',
            'clock_out_at'       => '退勤時刻',
            'breaks'             => '休憩時間',
            'breaks.*.start'     => '休憩開始時刻',
            'breaks.*.end'       => '休憩終了時刻',
            'notes'              => '備考',
        ];
    }

    public function messages(): array
    {
        return [
            'regex'          => ':attribute はHH:MM形式で入力してください。',
            'notes.required' => '備考を記入してください',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $in  = $this->input('clock_in_at');
            $out = $this->input('clock_out_at');

            if ($in && $out) {
                $inC  = Carbon::parse($in);
                $outC = Carbon::parse($out);
                if ($inC->gte($outC)) {
                    $v->errors()->add('clock_in_at', '出勤時間もしくは退勤時間が不適切な値です');
                }
            }

            $breaks = $this->input('breaks', []);
            foreach ($breaks as $i => $b) {
                $bs = $b['start'] ?? null;
                $be = $b['end'] ?? null;

                if ($bs) {
                    if ($in && Carbon::parse($bs)->lt(Carbon::parse($in))) {
                        $v->errors()->add("breaks.$i.start", '休憩時間が不適切な値です');
                    }
                    if ($out && Carbon::parse($bs)->gt(Carbon::parse($out))) {
                        $v->errors()->add("breaks.$i.start", '休憩時間が不適切な値です');
                    }
                }

                if ($be) {
                    if ($out && Carbon::parse($be)->gt(Carbon::parse($out))) {
                        $v->errors()->add("breaks.$i.end", '休憩時間もしくは退勤時間が不適切な値です');
                    }
                }
            }
        });
    }
}