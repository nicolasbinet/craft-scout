<?php

namespace rias\scout\tests;

use Codeception\Test\Unit;
use Craft;
use craft\elements\Entry;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use FakeEngine;
use rias\scout\controllers\IndexController;
use rias\scout\Scout;
use rias\scout\ScoutIndex;

class IndexControllerTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /** @var Scout */
    private $scout;

    protected function _before()
    {
        parent::_before();

        $scout = new Scout('scout');
        $scout->setSettings([
            'queue'   => false,
            'engine'  => FakeEngine::class,
            'indices' => [
                ScoutIndex::create('blog_nl')
                    ->criteria(function ($query) {
                        return $query;
                    }),
                ScoutIndex::create('blog_en')
                    ->criteria(function ($query) {
                        return $query;
                    }),
            ],
        ]);
        $scout->init();

        $section = new Section([
            'name'         => 'News',
            'handle'       => 'news',
            'type'         => Section::TYPE_CHANNEL,
            'siteSettings' => [
                new Section_SiteSettings([
                    'siteId'           => Craft::$app->getSites()->getPrimarySite()->id,
                    'enabledByDefault' => true,
                    'hasUrls'          => true,
                    'uriFormat'        => 'foo/{slug}',
                    'template'         => 'foo/_entry',
                ]),
            ],
        ]);

        Craft::$app->getSections()->saveSection($section);

        $element = new Entry();
        $element->siteId = 1;
        $element->sectionId = $section->id;
        $element->typeId = $section->getEntryTypes()[0]->id;
        $element->title = 'A new beginning.';
        $element->slug = 'a-new-beginning';

        Craft::$app->getElements()->saveElement($element);

        $this->scout = $scout;

        Craft::$app->getCache()->flush();
    }

    /** @test * */
    public function it_can_flush_an_index()
    {
        $this->tester->mockCraftMethods('request', [
            'getIsPost'            => true,
            'getRequiredBodyParam' => 'blog_nl',
        ]);

        $controller = new IndexController('scout', $this->scout);

        $controller->actionFlush();

        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_nl-flushCalled'));
        $this->assertEquals(false, Craft::$app->getCache()->get('scout-blog_fr-flushCalled'));
    }

    /** @test * */
    public function it_can_import_an_index()
    {
        $this->tester->mockCraftMethods('request', [
            'getIsPost'            => true,
            'getRequiredBodyParam' => 'blog_nl',
        ]);

        $controller = new IndexController('scout', $this->scout);

        $controller->actionImport();

        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_nl-updateCalled'));
        $this->assertEquals(false, Craft::$app->getCache()->get('scout-blog_fr-updateCalled'));
    }

    /** @test * */
    public function it_can_import_an_index_queued()
    {
        $this->scout->setSettings(['queue' => true]);

        $this->tester->mockCraftMethods('request', [
            'getIsPost'            => true,
            'getRequiredBodyParam' => 'blog_nl',
        ]);

        $controller = new IndexController('scout', $this->scout);

        $controller->actionImport();

        $this->assertEquals(false, Craft::$app->getCache()->get('scout-blog_nl-updateCalled'));
        $this->assertEquals(false, Craft::$app->getCache()->get('scout-blog_fr-updateCalled'));

        $this->tester->assertPushedToQueue('Indexing element(s) in “blog_nl”');
        Craft::$app->getQueue()->run();

        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_nl-updateCalled'));
        $this->assertEquals(false, Craft::$app->getCache()->get('scout-blog_fr-updateCalled'));
    }

    /** @test * */
    public function it_can_refresh_an_index()
    {
        $this->tester->mockCraftMethods('request', [
            'getIsPost'            => true,
            'getRequiredBodyParam' => 'blog_nl',
        ]);

        $controller = new IndexController('scout', $this->scout);

        $controller->actionRefresh();

        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_nl-flushCalled'));
        $this->assertEquals(1, Craft::$app->getCache()->get('scout-blog_nl-updateCalled'));
        $this->assertEquals(false, Craft::$app->getCache()->get('scout-blog_fr-flushCalled'));
        $this->assertEquals(false, Craft::$app->getCache()->get('scout-blog_fr-updateCalled'));
    }
}
