<?php

use App\Support\Fmt;

if (! function_exists('rupiah')) {
    function rupiah(int|float|null $n, bool $prefix = true): string
    {
        return Fmt::rupiah($n, $prefix);
    }
}

if (! function_exists('tgl')) {
    function tgl($d, bool $short = false): string
    {
        return Fmt::date($d, $short);
    }
}

if (! function_exists('tgljam')) {
    function tgljam($d): string
    {
        return Fmt::datetime($d);
    }
}
