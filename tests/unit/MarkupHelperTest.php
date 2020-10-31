<?php

namespace yoannisj\tailor\tests;

use Codeception\Test\Unit;

use UnitTester;
use Craft;

/**
 * Tests ran on the `\yoannisj\tailor\helpers\MarkupHelper` class
 */

class MarkupHelperTest extends Unit
{
    // =Properties Methods
    // =========================================================================

    /**
     * @var UnitTester
     */

    protected $tester;

    // =Public Methods
    // =========================================================================

    // =Tests
    // -------------------------------------------------------------------------

    /**
     * Tests the `\yoannisj\tailor\helpers\MarkupHelper::parseAttributes()` method
     */

    public function testParseAttributes()
    {
        Craft::$app->setEdition(Craft::Pro);

        $this->assertSame(
            Craft::Pro,
            Craft::$app->getEdition());
    }

    // =Protected Methods
    // =========================================================================

    // =Private Methods
    // =========================================================================
}
