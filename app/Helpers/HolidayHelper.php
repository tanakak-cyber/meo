<?php

namespace App\Helpers;

use Carbon\Carbon;

class HolidayHelper
{
    /**
     * 日本の祝日を判定する
     * 
     * @param Carbon $date
     * @return bool
     */
    public static function isHoliday(Carbon $date): bool
    {
        $year = $date->year;
        $month = $date->month;
        $day = $date->day;
        
        // 固定祝日
        $fixedHolidays = [
            // 1月
            [$year, 1, 1],   // 元日
            [$year, 1, 2],   // 休日（元日の振替）
            [$year, 1, 3],   // 休日（元日の振替）
            [$year, 1, 9],   // 成人の日（第2月曜日、後で計算）
            [$year, 2, 11],  // 建国記念の日
            [$year, 2, 23],  // 天皇誕生日
            [$year, 3, 20],  // 春分の日（後で計算）
            [$year, 3, 21],  // 春分の日（後で計算）
            [$year, 4, 29],  // 昭和の日
            [$year, 5, 3],   // 憲法記念日
            [$year, 5, 4],   // みどりの日
            [$year, 5, 5],   // こどもの日
            [$year, 7, 15],  // 海の日（第3月曜日、後で計算）
            [$year, 8, 11],  // 山の日
            [$year, 9, 22],  // 秋分の日（後で計算）
            [$year, 9, 23],  // 秋分の日（後で計算）
            [$year, 10, 9],  // スポーツの日（第2月曜日、後で計算）
            [$year, 11, 3],  // 文化の日
            [$year, 11, 23], // 勤労感謝の日
        ];
        
        // 固定祝日チェック（移動祝日を除く）
        foreach ($fixedHolidays as $holiday) {
            if ($holiday[0] == $year && $holiday[1] == $month && $holiday[2] == $day) {
                // 移動祝日でない場合のみ
                if (!in_array([$year, $month, $day], [
                    [$year, 1, 9],   // 成人の日
                    [$year, 7, 15],  // 海の日
                    [$year, 10, 9],  // スポーツの日
                ])) {
                    return true;
                }
            }
        }
        
        // 移動祝日チェック
        if (self::isComingOfAgeDay($year, $month, $day)) {
            return true;
        }
        
        if (self::isMarineDay($year, $month, $day)) {
            return true;
        }
        
        if (self::isSportsDay($year, $month, $day)) {
            return true;
        }
        
        // 春分の日・秋分の日チェック
        if (self::isVernalEquinoxDay($year, $month, $day)) {
            return true;
        }
        
        if (self::isAutumnalEquinoxDay($year, $month, $day)) {
            return true;
        }
        
        // 振替休日チェック
        if (self::isSubstituteHoliday($date)) {
            return true;
        }
        
        // 国民の休日チェック
        if (self::isCitizensHoliday($date)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 成人の日（1月第2月曜日）
     */
    private static function isComingOfAgeDay(int $year, int $month, int $day): bool
    {
        if ($month != 1) {
            return false;
        }
        
        // 1月の第2月曜日を計算
        $firstMonday = Carbon::create($year, 1, 1)->startOfMonth();
        while ($firstMonday->dayOfWeek != Carbon::MONDAY) {
            $firstMonday->addDay();
        }
        $secondMonday = $firstMonday->copy()->addWeek();
        
        return $day == $secondMonday->day;
    }
    
    /**
     * 海の日（7月第3月曜日、2020年以前は7月20日固定）
     */
    private static function isMarineDay(int $year, int $month, int $day): bool
    {
        if ($month != 7) {
            return false;
        }
        
        if ($year <= 2020) {
            return $day == 20;
        }
        
        // 7月の第3月曜日を計算
        $firstMonday = Carbon::create($year, 7, 1)->startOfMonth();
        while ($firstMonday->dayOfWeek != Carbon::MONDAY) {
            $firstMonday->addDay();
        }
        $thirdMonday = $firstMonday->copy()->addWeeks(2);
        
        return $day == $thirdMonday->day;
    }
    
    /**
     * スポーツの日（10月第2月曜日、2020年以前は10月10日固定）
     */
    private static function isSportsDay(int $year, int $month, int $day): bool
    {
        if ($month != 10) {
            return false;
        }
        
        if ($year <= 2020) {
            return $day == 10;
        }
        
        // 10月の第2月曜日を計算
        $firstMonday = Carbon::create($year, 10, 1)->startOfMonth();
        while ($firstMonday->dayOfWeek != Carbon::MONDAY) {
            $firstMonday->addDay();
        }
        $secondMonday = $firstMonday->copy()->addWeek();
        
        return $day == $secondMonday->day;
    }
    
    /**
     * 春分の日
     */
    private static function isVernalEquinoxDay(int $year, int $month, int $day): bool
    {
        if ($month != 3) {
            return false;
        }
        
        // 春分の日の計算式（簡易版）
        $vernalEquinoxDay = floor(20.8431 + 0.242194 * ($year - 1980) - floor(($year - 1980) / 4));
        if ($year >= 2100) {
            $vernalEquinoxDay = floor(21.8510 + 0.242194 * ($year - 1980) - floor(($year - 1980) / 4));
        }
        
        return $day == $vernalEquinoxDay;
    }
    
    /**
     * 秋分の日
     */
    private static function isAutumnalEquinoxDay(int $year, int $month, int $day): bool
    {
        if ($month != 9) {
            return false;
        }
        
        // 秋分の日の計算式（簡易版）
        $autumnalEquinoxDay = floor(23.2488 + 0.242194 * ($year - 1980) - floor(($year - 1980) / 4));
        if ($year >= 2100) {
            $autumnalEquinoxDay = floor(24.2488 + 0.242194 * ($year - 1980) - floor(($year - 1980) / 4));
        }
        
        return $day == $autumnalEquinoxDay;
    }
    
    /**
     * 振替休日（祝日が日曜の場合、その次の平日）
     */
    private static function isSubstituteHoliday(Carbon $date): bool
    {
        // 今日が月曜日で、前日（日曜日）が祝日の場合
        if ($date->dayOfWeek == Carbon::MONDAY) {
            $prevDay = $date->copy()->subDay();
            if (self::isFixedHoliday($prevDay)) {
                return true;
            }
        }
        
        // 連続する祝日の場合、最初の祝日が日曜なら次の平日が振替休日
        // より正確には、前日が日曜で祝日の場合
        $prevDay = $date->copy()->subDay();
        if ($prevDay->dayOfWeek == Carbon::SUNDAY && self::isFixedHoliday($prevDay)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 固定祝日かどうか（振替休日判定用）
     */
    private static function isFixedHoliday(Carbon $date): bool
    {
        $year = $date->year;
        $month = $date->month;
        $day = $date->day;
        
        $fixedHolidays = [
            [$year, 1, 1],   // 元日
            [$year, 2, 11],  // 建国記念の日
            [$year, 2, 23],  // 天皇誕生日
            [$year, 4, 29],  // 昭和の日
            [$year, 5, 3],   // 憲法記念日
            [$year, 5, 4],   // みどりの日
            [$year, 5, 5],   // こどもの日
            [$year, 8, 11],  // 山の日
            [$year, 11, 3],  // 文化の日
            [$year, 11, 23], // 勤労感謝の日
        ];
        
        foreach ($fixedHolidays as $holiday) {
            if ($holiday[0] == $year && $holiday[1] == $month && $holiday[2] == $day) {
                return true;
            }
        }
        
        // 移動祝日も含める
        if (self::isComingOfAgeDay($year, $month, $day) ||
            self::isMarineDay($year, $month, $day) ||
            self::isSportsDay($year, $month, $day) ||
            self::isVernalEquinoxDay($year, $month, $day) ||
            self::isAutumnalEquinoxDay($year, $month, $day)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 国民の休日（祝日と祝日の間の平日）
     */
    private static function isCitizensHoliday(Carbon $date): bool
    {
        // 前日と翌日が祝日で、今日が平日の場合
        $prevDay = $date->copy()->subDay();
        $nextDay = $date->copy()->addDay();
        
        if (self::isFixedHoliday($prevDay) && self::isFixedHoliday($nextDay)) {
            $dayOfWeek = $date->dayOfWeek;
            if ($dayOfWeek != Carbon::SUNDAY && $dayOfWeek != Carbon::SATURDAY) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 平日かどうか（土日祝を除外）
     */
    public static function isWeekday(Carbon $date): bool
    {
        $dayOfWeek = $date->dayOfWeek;
        
        // 土日を除外
        if ($dayOfWeek == Carbon::SUNDAY || $dayOfWeek == Carbon::SATURDAY) {
            return false;
        }
        
        // 祝日を除外
        if (self::isHoliday($date)) {
            return false;
        }
        
        return true;
    }
}

