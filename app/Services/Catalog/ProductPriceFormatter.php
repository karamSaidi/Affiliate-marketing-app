<?php

namespace App\Services\Catalog;

class ProductPriceFormatter
{
    public function format(int $minorUnits, string $currency, string $locale): string
    {
        $whole = intdiv($minorUnits, 10000);
        $fraction = rtrim(str_pad((string) ($minorUnits % 10000), 4, '0', STR_PAD_LEFT), '0');
        $decimal = $locale === 'ar' ? '٫' : '.';
        $group = $locale === 'ar' ? '٬' : ',';
        $wholeText = implode($group, array_reverse(str_split(strrev((string) $whole), 3)));
        $amount = $wholeText.($fraction === '' ? '' : $decimal.$fraction);

        if ($locale === 'ar') {
            $amount = strtr($amount, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
        }

        return $amount.' '.$currency;
    }
}
