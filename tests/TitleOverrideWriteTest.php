<?php

namespace Restruct\LoginBranding\Tests;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * application_name_overrides_title must never be written back as the stored SiteConfig.Title,
 * including by forceChange()->write(), which marks every field changed.
 *
 * Regression: the override sits in the record's "original" state, so a plain write() left Title alone,
 * but forceChange()->write() stored application_name as the site title on both majors.
 */
class TitleOverrideWriteTest extends SapphireTest
{
    protected $usesDatabase = true;

    private int $id;

    protected function setUp(): void
    {
        parent::setUp();

        // A stored title that differs from the application name, so a write-back is observable.
        $config = SiteConfig::current_site_config();
        $config->Title = 'Stored Title';
        $config->write();
        $this->id = $config->ID;

        Config::modify()->set(LeftAndMain::class, 'application_name', 'Branded App');
        Config::modify()->set(SiteConfig::class, 'application_name_overrides_title', true);
    }

    /** A fresh load, so the override is applied at hydration exactly as on a real request. */
    private function load(): SiteConfig
    {
        return SiteConfig::get()->byID($this->id);
    }

    /** What is in the database, read with the override off. */
    private function storedTitle(): ?string
    {
        Config::modify()->set(SiteConfig::class, 'application_name_overrides_title', false);
        $title = SiteConfig::get()->byID($this->id)->Title;
        Config::modify()->set(SiteConfig::class, 'application_name_overrides_title', true);

        return $title;
    }

    public function testForcedWriteDoesNotStoreTheApplicationName()
    {
        $config = $this->load();
        $this->assertSame('Branded App', $config->Title, 'precondition: the override is applied');

        $config->forceChange()->write();

        $this->assertSame('Stored Title', $this->storedTitle());
    }

    public function testRecordKeepsTheOverrideInMemoryAfterAForcedWrite()
    {
        $config = $this->load();
        $config->forceChange()->write();

        $this->assertSame('Branded App', $config->Title);
        $this->assertFalse($config->isChanged('Title'), 'the re-applied override must not mark the record dirty');
    }

    public function testRepeatedForcedWritesDoNotStoreTheApplicationName()
    {
        $config = $this->load();
        $config->forceChange()->write();
        $config->forceChange()->write();

        $this->assertSame('Stored Title', $this->storedTitle());
    }

    public function testForcedWriteDoesNotStoreTheApplicationNameWhenTheStoredTitleIsEmpty()
    {
        $config = $this->load();
        $config->Title = '';
        $config->write();

        $config = $this->load();
        $config->forceChange()->write();

        $this->assertEmpty($this->storedTitle());
    }

    /**
     * With application_name_clear_fields: false the Title field stays in Settings; a title typed
     * there must still be stored, forced write or not.
     */
    public function testATypedTitleIsStillStored()
    {
        $config = $this->load();
        $config->Title = 'Typed Title';
        $config->write();
        $this->assertSame('Typed Title', $this->storedTitle());

        $config = $this->load();
        $config->Title = 'Typed Again';
        $config->forceChange()->write();
        $this->assertSame('Typed Again', $this->storedTitle());

        // ...and the forced write after it keeps that newly stored title.
        $config->forceChange()->write();
        $this->assertSame('Typed Again', $this->storedTitle());
    }
}
