<?php

namespace App\Support;

use Carbon\Carbon;

class PatientAttentionSummary
{
    public static function make(array $rows, int $year, ?int $month = null): array
    {
        $months=array_fill(1,12,0);$years=[$year];$clinics=[];$professionals=[];
        foreach ($rows as $row) {
            $date=Carbon::parse($row['date'])->timezone(config('app.timezone'));
            $years[]=$date->year;
            if ($date->year!==$year) continue;
            $months[$date->month]++;
            if ($month && $date->month!==$month) continue;
            foreach (['clinics'=>'clinic','professionals'=>'professional'] as $group=>$field) {
                $key=$row[$field.'_id'];
                ${$group}[$key]??=['name'=>$row[$field], 'count'=>0];
                ${$group}[$key]['count']++;
            }
        }
        $rank=static function(array $items): array {
            usort($items,static fn($a,$b)=>($b['count']<=>$a['count']) ?: strcmp($a['name'],$b['name']));
            return array_slice($items,0,5);
        };
        $years=array_values(array_unique($years));rsort($years);
        return ['year'=>$year,'years'=>$years,'month'=>$month,'months'=>$months,'total'=>$month ? $months[$month] : array_sum($months),
            'clinics'=>$rank($clinics),'professionals'=>$rank($professionals)];
    }
}
