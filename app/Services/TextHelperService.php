<?php
namespace App\Services;

class TextHelperService
{
    public static function detectMode(string $q): string
    {
        $q = trim($q);
        if ($q === '')
            return 'free_text';

        // Nếu là URL/chuỗi có www → tách host
        $candidate = $q;
        if (preg_match('#^https?://#i', $q)) {
            $host = parse_url($q, PHP_URL_HOST);
            if ($host)
                $candidate = $host;
        } elseif (stripos($q, 'www.') === 0) {
            $candidate = $q; // sẽ xử lý như host
        }

        // Chuỗi còn lại không được có khoảng trắng nếu là domain
        if (preg_match('/\s/u', $candidate)) {
            return str_word_count($q) <= 3 ? 'entity' : 'free_text';
        }

        // Chuẩn hoá IDN (nếu extension intl có sẵn)
        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($candidate, 0, INTL_IDNA_VARIANT_UTS46);
            if ($ascii !== false)
                $candidate = $ascii;
        }

        // BẮT BUỘC có ít nhất 1 dấu chấm & TLD chữ cái >= 2
        // label: a-z0-9 hoặc -, không bắt đầu/kết thúc bằng -, max 63 ký tự/label
        $domainRegex = '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i';

        if (preg_match($domainRegex, $candidate)) {
            return 'domain';
        }

        // Nếu không khớp domain -> entity/free_text
        return str_word_count($q) <= 3 ? 'entity' : 'free_text';
    }


    public static function classifyIntent(string $q): string
    {
        $t = mb_strtolower($q);
        if (preg_match('/(mua|giá|đặt|khuyến mãi|so sánh|tốt nhất|đăng ký|giỏ)/u', $t))
            return 'transactional';
        if (preg_match('/(trang chủ|liên hệ|địa chỉ|ở đâu|website|đi tới)/u', $t))
            return 'navigational';
        return 'informational';
    }

    public static function jaccard(string $a, string $b): float
    {
        $ta = collect(preg_split('/\W+/u', mb_strtolower($a)))->filter()->unique();
        $tb = collect(preg_split('/\W+/u', mb_strtolower($b)))->filter()->unique();
        $inter = $ta->intersect($tb)->count();
        $union = $ta->merge($tb)->unique()->count();
        return $union ? $inter / $union : 0.0;
    }

    public static function hash(string $s): string
    {
        return sha1(mb_strtolower(trim($s)));
    }
    public static function urlHash(string $u): string
    {
        return sha1(mb_strtolower(trim($u)));
    }
}
