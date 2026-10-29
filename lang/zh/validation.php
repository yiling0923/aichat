<?php

return [
    'required' => '请填写“:attribute”。',
    'email' => '“:attribute”必须是有效的邮箱地址。',
    'confirmed' => '两次输入的“:attribute”不一致。',
    'unique' => '“:attribute”已经被使用。',
    'in' => '“:attribute”的选择无效。',
    'integer' => '“:attribute”必须是整数。',
    'numeric' => '“:attribute”必须是数字。',
    'date' => '“:attribute”不是有效的日期。',
    'date_format' => '“:attribute”的格式不正确。',
    'boolean' => '“:attribute”必须是是或否。',
    'array' => '“:attribute”必须是列表。',
    'exists' => '所选的“:attribute”无效。',
    'url' => '“:attribute”必须是有效的网址。',
    'string' => '“:attribute”必须是文字。',
    'min' => [
        'string' => '“:attribute”至少需要 :min 个字符。',
        'numeric' => '“:attribute”不能小于 :min。',
        'array' => '“:attribute”至少需要 :min 项。',
    ],
    'max' => [
        'string' => '“:attribute”不能超过 :max 个字符。',
        'numeric' => '“:attribute”不能大于 :max。',
        'array' => '“:attribute”最多 :max 项。',
    ],
    'between' => [
        'numeric' => '“:attribute”必须在 :min 到 :max 之间。',
        'string' => '“:attribute”必须在 :min 到 :max 个字符之间。',
    ],
    'attributes' => [],
];
