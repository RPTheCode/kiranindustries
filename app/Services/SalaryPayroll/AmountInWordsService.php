<?php

namespace App\Services\SalaryPayroll;

class AmountInWordsService
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    public function rupees(float $amount): string
    {
        $amount = round($amount, 2);
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        $words = $this->convertIndian($rupees).' Rupees';

        if ($paise > 0) {
            $words .= ' and '.$this->convertIndian($paise).' Paise';
        }

        return $words.' Only';
    }

    private function convertIndian(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $parts = [];

        $crore = intdiv($number, 10000000);
        $number %= 10000000;
        $lakh = intdiv($number, 100000);
        $number %= 100000;
        $thousand = intdiv($number, 1000);
        $number %= 1000;
        $hundred = intdiv($number, 100);
        $number %= 100;

        if ($crore > 0) {
            $parts[] = $this->convertBelowThousand($crore).' Crore';
        }
        if ($lakh > 0) {
            $parts[] = $this->convertBelowThousand($lakh).' Lakh';
        }
        if ($thousand > 0) {
            $parts[] = $this->convertBelowThousand($thousand).' Thousand';
        }
        if ($hundred > 0) {
            $parts[] = self::ONES[$hundred].' Hundred';
        }
        if ($number > 0) {
            if ($hundred > 0) {
                $parts[] = 'and '.$this->convertBelowThousand($number);
            } else {
                $parts[] = $this->convertBelowThousand($number);
            }
        }

        return implode(' ', $parts);
    }

    private function convertBelowThousand(int $number): string
    {
        if ($number < 20) {
            return self::ONES[$number];
        }

        $ten = intdiv($number, 10);
        $one = $number % 10;

        return trim(self::TENS[$ten].($one > 0 ? ' '.self::ONES[$one] : ''));
    }
}
