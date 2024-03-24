<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Selenium;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;

use function strtolower;

#[CoversNothing]
#[Large]
class ExportTest extends TestBase
{
    /**
     * Setup the browser environment to run the selenium test case
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->dbQuery(
            'USE `' . $this->databaseName . '`;'
            . 'CREATE TABLE `test_table` ('
            . ' `id` int(11) NOT NULL AUTO_INCREMENT,'
            . ' `val` int(11) NOT NULL,'
            . ' PRIMARY KEY (`id`)'
            . ');'
            . 'INSERT INTO `test_table` (val) VALUES (2);',
        );

        $this->login();
    }

    /**
     * Test for server level export
     *
     * @param string   $plugin   Export format
     * @param string[] $expected Array of expected strings
     */
    #[DataProvider('exportDataProvider')]
    public function testServerExport(string $plugin, array $expected): void
    {
        $text = $this->doExport('server', $plugin);

        foreach ($expected as $str) {
            self::assertStringContainsString($str, $text);
        }
    }

    /**
     * Test for db level export
     *
     * @param string   $plugin   Export format
     * @param string[] $expected Array of expected strings
     */
    #[DataProvider('exportDataProvider')]
    public function testDbExport(string $plugin, array $expected): void
    {
        $this->navigateDatabase($this->databaseName);

        $text = $this->doExport('db', $plugin);

        foreach ($expected as $str) {
            self::assertStringContainsString($str, $text);
        }
    }

    /**
     * Test for table level export
     *
     * @param string   $plugin   Export format
     * @param string[] $expected Array of expected strings
     */
    #[DataProvider('exportDataProvider')]
    public function testTableExport(string $plugin, array $expected): void
    {
        $this->dbQuery('INSERT INTO `' . $this->databaseName . '`.`test_table` (val) VALUES (3);');

        $this->navigateTable('test_table');

        $text = $this->doExport('table', $plugin);

        foreach ($expected as $str) {
            self::assertStringContainsString($str, $text);
        }
    }

    /**
     * @return array<int, array<int, string|array<int, string>>>
     * @psalm-return array<int, array{string, string[]}>
     */
    public function exportDataProvider(): array
    {
        return [
            ['CSV', ['"1","2"']],
            [
                'SQL',
                ['CREATE TABLE IF NOT EXISTS `test_table`', 'INSERT INTO `test_table` (`id`, `val`) VALUES', '(1, 2)'],
            ],
            ['JSON', ['{"id":"1","val":"2"}']],
        ];
    }

    /**
     * Function that goes to the import page, uploads a file and submit form
     *
     * @param string $type   level: server, db or import
     * @param string $plugin format: csv, json, etc
     *
     * @return string export string
     */
    private function doExport(string $type, string $plugin): string
    {
        $this->expandMore();
        $this->waitForElement('partialLinkText', 'Export')->click();
        $this->waitAjax();

        $this->waitForElement('id', 'quick_or_custom');
        $this->byCssSelector('label[for=radio_custom_export]')->click();

        $this->selectByLabel($this->byId('plugins'), $plugin);

        if ($type === 'server') {
            $this->scrollIntoView('databases_and_tables', 200);
            $this->byId('db_unselect_all')->click();

            $this->byCssSelector('option[value="' . $this->databaseName . '"]')->click();
        }

        if ($type === 'table') {
            $this->scrollIntoView('radio_allrows_0');
            $this->byCssSelector('label[for=radio_allrows_0]')->click();
            $this->scrollIntoView('limit_to');
            $this->byName('limit_to')->clear();
            $this->byName('limit_to')->sendKeys('1');
        }

        $this->scrollIntoView('radio_view_as_text');
        $this->byCssSelector('label[for=radio_view_as_text]')->click();

        $this->waitUntilElementIsVisible('id', 'format_specific_opts');
        $this->scrollIntoView('format_specific_opts');
        $this->waitUntilElementIsVisible('id', strtolower($plugin) . '_options');
        $this->scrollIntoView(strtolower($plugin) . '_options');

        if ($plugin === 'SQL') {
            if ($type !== 'db') {
                $this->waitUntilElementIsVisible('id', 'radio_sql_structure_or_data_structure_and_data');
                $this->scrollIntoView('radio_sql_structure_or_data_structure_and_data');
                $this->byCssSelector('label[for=radio_sql_structure_or_data_structure_and_data]')->click();
            }

            $this->waitUntilElementIsVisible('id', 'checkbox_sql_if_not_exists');
            $this->scrollIntoView('checkbox_sql_if_not_exists');
            $ele = $this->byId('checkbox_sql_if_not_exists');
            if (! $ele->isSelected()) {
                $this->waitForElement('cssSelector', 'label[for=checkbox_sql_if_not_exists]')->click();
            }
        }

        $this->scrollToBottom();

        $this->waitForElement('id', 'buttonGo')->click();

        $this->waitAjax();

        return $this->waitForElement('id', 'textSQLDUMP')->getText();
    }
}
