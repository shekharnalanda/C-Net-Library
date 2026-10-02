<?php

namespace App\Services;

use DateTimeImmutable;
use InvalidArgumentException;

final class DailySeatWindow
{
    public static function minutes(string $time): int
    {
        if (!preg_match('/^(\d{2}):(\d{2})(?::00)?$/', $time, $m) || (int)$m[1]>23 || (int)$m[2]>59) {
            throw new InvalidArgumentException('Use a valid time in HH:MM format.');
        }
        return (int)$m[1]*60+(int)$m[2];
    }

    public static function segments(?string $start, ?string $end): array
    {
        if (!$start || !$end) return [[0,0,1440]];
        $s=self::minutes($start); $e=self::minutes($end);
        if ($s===$e) return [[0,0,1440]];
        if ($e>$s) return [[0,$s,$e]];
        return $e===0 ? [[0,$s,1440]] : [[0,$s,1440],[1,0,$e]];
    }

    public static function overlaps(string $from, ?string $to, ?string $start, ?string $end,
        string $otherFrom, ?string $otherTo, ?string $otherStart, ?string $otherEnd): bool
    {
        foreach(self::segments($start,$end) as [$offset,$s,$e]) {
            foreach(self::segments($otherStart,$otherEnd) as [$otherOffset,$os,$oe]) {
                if ($s >= $oe || $os >= $e) continue;
                $aFrom=self::shift($from,$offset); $bFrom=self::shift($otherFrom,$otherOffset);
                $aTo=$to===null ? null : self::shift($to,$offset);
                $bTo=$otherTo===null ? null : self::shift($otherTo,$otherOffset);
                if (($aTo===null || $bFrom<=$aTo) && ($bTo===null || $aFrom<=$bTo)) return true;
            }
        }
        return false;
    }

    public static function onDate(string $from, ?string $to, ?string $start, ?string $end, string $date): array
    {
        $windows=[];
        foreach(self::segments($start,$end) as [$offset,$s,$e]) {
            $anchor=self::shift($date,-$offset);
            if ($anchor >= $from && ($to===null || $anchor <= $to)) $windows[]=[$s,$e];
        }
        return $windows;
    }

    public static function label(int $minutes): string
    {
        return sprintf('%02d:%02d',intdiv($minutes,60),$minutes%60);
    }

    public static function unpaidDeadline(string $start): string
    {
        $date=self::date($start);
        return max($start,$date->format('Y-m').'-10');
    }

    public static function renewalDeadline(string $expiry): string
    {
        $next=self::date($expiry)->modify('+1 day');
        $deadline=$next->format('Y-m').'-10';
        return $deadline >= $next->format('Y-m-d') ? $deadline : $next->modify('first day of next month')->format('Y-m').'-10';
    }

    private static function shift(string $date,int $days): string
    {
        return self::date($date)->modify(($days>=0?'+':'').$days.' days')->format('Y-m-d');
    }

    private static function date(string $date): DateTimeImmutable
    {
        $value=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$value || $value->format('Y-m-d')!==$date) throw new InvalidArgumentException('Invalid calendar date.');
        return $value;
    }
}
