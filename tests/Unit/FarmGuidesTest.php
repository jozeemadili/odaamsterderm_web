<?php

namespace Tests\Unit;

use App\Services\Ai\FarmGuides;
use PHPUnit\Framework\TestCase;

class FarmGuidesTest extends TestCase
{
    private function guides(): FarmGuides
    {
        return new FarmGuides(dirname(__DIR__, 2).'/database/data/farm-guides');
    }

    public function test_kiswahili_questions_find_the_broiler_handbook_pages(): void
    {
        $pages = $this->guides()->relevant('Nina vifaranga 500 vya kuku wa nyama, nafasi ya banda iwe kiasi gani?');

        $this->assertNotEmpty($pages);
        $this->assertSame('Ross Broiler Handbook', $pages[0]['guide']);
        $this->assertStringContainsString('Stocking Density', $pages[0]['text']);
    }

    public function test_layer_questions_go_to_the_layer_guide(): void
    {
        $pages = $this->guides()->relevant('Kuku wa mayai wanaanza kutaga wiki ngapi?');

        $this->assertSame('Hy-Line Brown Layers Guide', $pages[0]['guide']);
    }

    public function test_unrelated_questions_get_no_excerpts(): void
    {
        $this->assertCount(0, $this->guides()->relevant('Who won the football match?'));
        $this->assertStringContainsString('(none matched this question)', $this->guides()->promptSection('hello'));
    }
}
