<?php

namespace App\Services\Experiments;

/**
 * The statistical method for A/B tests (frequentist, fixed horizon):
 *  - conversion rate: two-proportion z-test (pooled for the p-value, unpooled for the interval)
 *  - revenue per visitor and AOV: Welch's t-test (unequal variances, Welch–Satterthwaite df)
 *  - 95% confidence, with a Bonferroni correction when several variants are compared to control
 */
class Stats
{
    /** Two-sided test of variant vs control conversion. */
    public static function proportions(int $nA, int $xA, int $nB, int $xB, float $alpha = 0.05): ?array
    {
        if ($nA < 1 || $nB < 1) {
            return null;
        }
        $pA = $xA / $nA;
        $pB = $xB / $nB;
        $pooled = ($xA + $xB) / ($nA + $nB);
        $sePooled = sqrt($pooled * (1 - $pooled) * (1 / $nA + 1 / $nB));
        $z = $sePooled > 0 ? ($pB - $pA) / $sePooled : 0.0;
        $se = sqrt($pA * (1 - $pA) / $nA + $pB * (1 - $pB) / $nB);
        $crit = self::normalQuantile(1 - $alpha / 2);

        return self::result($pA, $pB, $pB - $pA, $se, $crit, 2 * (1 - self::normalCdf(abs($z))), $z);
    }

    /**
     * Welch's t-test from summary statistics.
     *
     * @param  array{n: int, sum: float, sumsq: float}  $a  control
     * @param  array{n: int, sum: float, sumsq: float}  $b  variant
     */
    public static function welch(array $a, array $b, float $alpha = 0.05): ?array
    {
        if ($a['n'] < 2 || $b['n'] < 2) {
            return null;
        }
        [$mA, $vA] = self::meanVariance($a);
        [$mB, $vB] = self::meanVariance($b);
        $sA = $vA / $a['n'];
        $sB = $vB / $b['n'];
        $se = sqrt($sA + $sB);
        if ($se == 0.0) {
            return self::result($mA, $mB, $mB - $mA, 0.0, 0.0, $mA == $mB ? 1.0 : 0.0, 0.0);
        }
        $t = ($mB - $mA) / $se;
        $df = ($sA + $sB) ** 2 / (($sA ** 2) / ($a['n'] - 1) + ($sB ** 2) / ($b['n'] - 1));
        $p = 2 * (1 - self::studentCdf(abs($t), $df));
        $crit = self::studentQuantile(1 - $alpha / 2, $df);

        return self::result($mA, $mB, $mB - $mA, $se, $crit, $p, $t) + ['df' => $df];
    }

    private static function result(float $control, float $variant, float $diff, float $se, float $crit, float $p, float $stat): array
    {
        return [
            'control' => $control,
            'variant' => $variant,
            'diff' => $diff,
            'low' => $diff - $crit * $se,
            'high' => $diff + $crit * $se,
            // Relative lift and its interval (relative to the control's value).
            'lift' => $control != 0.0 ? $diff / $control * 100 : null,
            'lift_low' => $control != 0.0 ? ($diff - $crit * $se) / $control * 100 : null,
            'lift_high' => $control != 0.0 ? ($diff + $crit * $se) / $control * 100 : null,
            'p' => max(0.0, min(1.0, $p)),
            'stat' => $stat,
        ];
    }

    /** @return array{0: float, 1: float} mean and sample variance */
    public static function meanVariance(array $s): array
    {
        $mean = $s['sum'] / $s['n'];
        $variance = $s['n'] > 1 ? max(0.0, ($s['sumsq'] - $s['n'] * $mean * $mean) / ($s['n'] - 1)) : 0.0;

        return [$mean, $variance];
    }

    /** Bonferroni: the per-comparison significance level. */
    public static function alpha(int $variants, float $confidence = 0.95): float
    {
        return (1 - $confidence) / max(1, $variants - 1);
    }

    public static function normalCdf(float $x): float
    {
        return 0.5 * (1 + self::erf($x / M_SQRT2));
    }

    /** Abramowitz–Stegun 7.1.26 with a refinement step; accurate to ~1e-7. */
    private static function erf(float $x): float
    {
        $sign = $x < 0 ? -1 : 1;
        $x = abs($x);
        $t = 1 / (1 + 0.3275911 * $x);
        $y = 1 - ((((1.061405429 * $t - 1.453152027) * $t + 1.421413741) * $t - 0.284496736) * $t + 0.254829592) * $t * exp(-$x * $x);

        return $sign * $y;
    }

    /** Inverse normal CDF (Acklam's algorithm). */
    public static function normalQuantile(float $p): float
    {
        $a = [-39.69683028665376, 220.9460984245205, -275.9285104469687, 138.3577518672690, -30.66479806614716, 2.506628277459239];
        $b = [-54.47609879822406, 161.5858368580409, -155.6989798598866, 66.80131188771972, -13.28068155288572];
        $c = [-0.007784894002430293, -0.3223964580411365, -2.400758277161838, -2.549732539343734, 4.374664141464968, 2.938163982698783];
        $d = [0.007784695709041462, 0.3224671290700398, 2.445134137142996, 3.754408661907416];
        $low = 0.02425;
        if ($p < $low) {
            $q = sqrt(-2 * log($p));

            return ((((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5]) / (((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1);
        }
        if ($p > 1 - $low) {
            return -self::normalQuantile(1 - $p);
        }
        $q = $p - 0.5;
        $r = $q * $q;

        return ((((($a[0] * $r + $a[1]) * $r + $a[2]) * $r + $a[3]) * $r + $a[4]) * $r + $a[5]) * $q / ((((($b[0] * $r + $b[1]) * $r + $b[2]) * $r + $b[3]) * $r + $b[4]) * $r + 1);
    }

    /** Student's t CDF through the regularized incomplete beta function. */
    public static function studentCdf(float $t, float $df): float
    {
        if ($df > 1e6) {
            return self::normalCdf($t);
        }
        $x = $df / ($df + $t * $t);
        $tail = 0.5 * self::betaInc($df / 2, 0.5, $x);

        return $t >= 0 ? 1 - $tail : $tail;
    }

    /** Inverse t CDF by bisection (the interval only needs a few digits). */
    public static function studentQuantile(float $p, float $df): float
    {
        [$lo, $hi] = [0.0, 50.0];
        for ($i = 0; $i < 80; $i++) {
            $mid = ($lo + $hi) / 2;
            if (self::studentCdf($mid, $df) < $p) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }

        return ($lo + $hi) / 2;
    }

    /** Regularized incomplete beta I_x(a, b) (continued fraction, Numerical Recipes). */
    private static function betaInc(float $a, float $b, float $x): float
    {
        if ($x <= 0) {
            return 0.0;
        }
        if ($x >= 1) {
            return 1.0;
        }
        $front = exp(self::logGamma($a + $b) - self::logGamma($a) - self::logGamma($b) + $a * log($x) + $b * log(1 - $x));
        if ($x < ($a + 1) / ($a + $b + 2)) {
            return $front * self::betaFraction($a, $b, $x) / $a;
        }

        return 1 - $front * self::betaFraction($b, $a, 1 - $x) / $b;
    }

    private static function betaFraction(float $a, float $b, float $x): float
    {
        $tiny = 1e-30;
        $c = 1.0;
        $d = 1 - ($a + $b) * $x / ($a + 1);
        $d = abs($d) < $tiny ? $tiny : $d;
        $d = 1 / $d;
        $h = $d;
        for ($m = 1; $m <= 300; $m++) {
            $m2 = 2 * $m;
            $aa = $m * ($b - $m) * $x / (($a + $m2 - 1) * ($a + $m2));
            $d = 1 + $aa * $d;
            $d = abs($d) < $tiny ? $tiny : $d;
            $c = 1 + $aa / $c;
            $c = abs($c) < $tiny ? $tiny : $c;
            $d = 1 / $d;
            $h *= $d * $c;
            $aa = -($a + $m) * ($a + $b + $m) * $x / (($a + $m2) * ($a + $m2 + 1));
            $d = 1 + $aa * $d;
            $d = abs($d) < $tiny ? $tiny : $d;
            $c = 1 + $aa / $c;
            $c = abs($c) < $tiny ? $tiny : $c;
            $d = 1 / $d;
            $delta = $d * $c;
            $h *= $delta;
            if (abs($delta - 1) < 3e-14) {
                break;
            }
        }

        return $h;
    }

    /** Lanczos approximation. */
    private static function logGamma(float $x): float
    {
        $g = [76.18009172947146, -86.50532032941677, 24.01409824083091, -1.231739572450155, 0.1208650973866179e-2, -0.5395239384953e-5];
        $y = $x;
        $tmp = $x + 5.5;
        $tmp -= ($x + 0.5) * log($tmp);
        $ser = 1.000000000190015;
        foreach ($g as $coef) {
            $ser += $coef / ++$y;
        }

        return -$tmp + log(2.5066282746310005 * $ser / $x);
    }
}
