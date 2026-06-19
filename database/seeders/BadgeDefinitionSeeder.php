<?php

namespace Database\Seeders;

use App\Models\BadgeDefinition;
use Illuminate\Database\Seeder;

class BadgeDefinitionSeeder extends Seeder
{
    private const XP_RATIO = 1.4; // Geometric progression ratio

    public function run(): void
    {
        // Clear existing badges
        BadgeDefinition::truncate();

        $badges = [];
        $baseXp = 100;

        // ===== READER TIER (I-VI) =====
        $readerXp = $baseXp;
        for ($i = 1; $i <= 6; $i++) {
            $badges[] = [
                'slug' => "reader_" . $this->romanNumeral($i),
                'name' => "Reader " . $this->romanNumeral($i),
                'tier' => 'Reader',
                'tier_level' => $i,
                'description' => $this->getReaderDescription($i),
                'icon' => 'fa-book',
                'color_class' => 'bg-blue-100 text-blue-600',
                'xp_required' => (int) round($readerXp),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $readerXp *= self::XP_RATIO;
        }

        // ===== MASTER TIER (I-V) =====
        $masterXp = $readerXp;
        for ($i = 1; $i <= 5; $i++) {
            $badges[] = [
                'slug' => "master_" . $this->romanNumeral($i),
                'name' => "Master " . $this->romanNumeral($i),
                'tier' => 'Master',
                'tier_level' => $i,
                'description' => $this->getMasterDescription($i),
                'icon' => 'fa-star',
                'color_class' => 'bg-amber-100 text-amber-600',
                'xp_required' => (int) round($masterXp),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $masterXp *= self::XP_RATIO;
        }

        // ===== GRAND MASTER TIER (I-V) =====
        $grandMasterXp = $masterXp;
        for ($i = 1; $i <= 5; $i++) {
            $badges[] = [
                'slug' => "grand_master_" . $this->romanNumeral($i),
                'name' => "Grand Master " . $this->romanNumeral($i),
                'tier' => 'Grand Master',
                'tier_level' => $i,
                'description' => $this->getGrandMasterDescription($i),
                'icon' => 'fa-crown',
                'color_class' => 'bg-purple-100 text-purple-600',
                'xp_required' => (int) round($grandMasterXp),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $grandMasterXp *= self::XP_RATIO;
        }

        // ===== LEGENDARY GENIUS TIER (I-III) =====
        $legendaryXp = $grandMasterXp;
        for ($i = 1; $i <= 3; $i++) {
            $badges[] = [
                'slug' => "legendary_genius_" . $this->romanNumeral($i),
                'name' => "Legendary Genius " . $this->romanNumeral($i),
                'tier' => 'Legendary Genius',
                'tier_level' => $i,
                'description' => $this->getLegendaryDescription($i),
                'icon' => 'fa-gem',
                'color_class' => 'bg-rose-100 text-rose-600',
                'xp_required' => (int) round($legendaryXp),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $legendaryXp *= self::XP_RATIO;
        }

        // Insert all badges
        BadgeDefinition::insert($badges);
    }

    private function romanNumeral(int $num): string
    {
        $romanNumerals = ['I', 'II', 'III', 'IV', 'V', 'VI'];
        return $romanNumerals[$num - 1] ?? 'I';
    }

    private function getReaderDescription(int $level): string
    {
        $descriptions = [
            "Starting your learning journey. You've taken the first step into the world of knowledge.",
            "Growing your reading habit. You're building momentum in your studies.",
            "Establishing consistency. You're becoming a regular learner.",
            "Strong foundation formed. Your dedication is paying off.",
            "Advanced reader status. You're diving deep into subjects.",
            "Expert reader achieved. You've mastered the basics and ready for the next challenge.",
        ];
        return $descriptions[$level - 1] ?? "Keep learning and growing.";
    }

    private function getMasterDescription(int $level): string
    {
        $descriptions = [
            "You've transcended basic learning. Welcome to the Master tier where true expertise begins.",
            "Deepening your mastery. You understand concepts on a profound level.",
            "Advanced techniques unlocked. You're applying knowledge creatively.",
            "Dominating your subjects. Your expertise is undeniable.",
            "Complete mastery achieved. You're at the pinnacle of focused learning.",
        ];
        return $descriptions[$level - 1] ?? "Continue your mastery journey.";
    }

    private function getGrandMasterDescription(int $level): string
    {
        $descriptions = [
            "Rising to legendary status. You've transcended mastery into true excellence.",
            "Commanding respect through knowledge. Few reach this level of achievement.",
            "Your expertise is exceptional. You've dedicated yourself to perfection.",
            "Commanding the highest standards. You set the bar for others.",
            "Apex of achievement. You've reached the Grand Master milestone.",
        ];
        return $descriptions[$level - 1] ?? "Achieve grand mastery.";
    }

    private function getLegendaryDescription(int $level): string
    {
        $descriptions = [
            "You've entered immortal status. Legendary Genius - a title earned through unwavering commitment.",
            "Your brilliance is undeniable. You represent the perfect fusion of knowledge and dedication.",
            "You are a beacon of excellence. Your achievements inspire generations.",
        ];
        return $descriptions[$level - 1] ?? "You are legendary.";
    }
}
