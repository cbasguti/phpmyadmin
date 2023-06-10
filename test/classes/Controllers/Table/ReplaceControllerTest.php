<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Controllers\Table;

use PhpMyAdmin\CheckUserPrivileges;
use PhpMyAdmin\ConfigStorage\Relation;
use PhpMyAdmin\ConfigStorage\RelationCleanup;
use PhpMyAdmin\ConfigStorage\RelationParameters;
use PhpMyAdmin\Controllers\Sql\SqlController;
use PhpMyAdmin\Controllers\Table\ChangeController;
use PhpMyAdmin\Controllers\Table\ReplaceController;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\FileListing;
use PhpMyAdmin\Http\ServerRequest;
use PhpMyAdmin\InsertEdit;
use PhpMyAdmin\Operations;
use PhpMyAdmin\Sql;
use PhpMyAdmin\Template;
use PhpMyAdmin\Tests\AbstractTestCase;
use PhpMyAdmin\Tests\Stubs\DbiDummy;
use PhpMyAdmin\Tests\Stubs\ResponseRenderer;
use PhpMyAdmin\Transformations;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(ReplaceController::class)]
class ReplaceControllerTest extends AbstractTestCase
{
    protected DatabaseInterface $dbi;

    protected DbiDummy $dummyDbi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dummyDbi = $this->createDbiDummy();
        $this->dbi = $this->createDatabaseInterface($this->dummyDbi);
        $GLOBALS['dbi'] = $this->dbi;
        $GLOBALS['server'] = 1;
        $GLOBALS['showtable'] = null;
        $GLOBALS['db'] = 'my_db';
        $GLOBALS['table'] = 'test_tbl';

        $GLOBALS['cfg']['Server']['user'] = 'user';
        $GLOBALS['cfg']['Server']['DisableIS'] = false;

        $relationParameters = RelationParameters::fromArray([
            'table_coords' => 'table_name',
            'displaywork' => true,
            'db' => 'information_schema',
            'table_info' => 'table_info',
            'relwork' => true,
            'relation' => 'relation',
            'mimework' => true,
            'commwork' => true,
            'column_info' => 'column_info',
            'pdf_pages' => 'pdf_pages',
            'bookmarkwork' => true,
            'bookmark' => 'bookmark',
            'uiprefswork' => true,
            'table_uiprefs' => 'table_uiprefs',
        ]);
        (new ReflectionClass(Relation::class))->getProperty('cache')->setValue(
            [$GLOBALS['server'] => $relationParameters],
        );
    }

    public function testReplace(): void
    {
        $GLOBALS['urlParams'] = [];
        $_POST['db'] = $GLOBALS['db'];
        $_POST['table'] = $GLOBALS['table'];
        $_POST['ajax_request'] = 'true';
        $_POST['clause_is_unique'] = 1;
        $_POST['where_clause'] = ['`test`.`ser` = 2', '`test`.`ser` = 1'];
        $_POST['rel_fields_list'] = '';
        $_POST['do_transformations'] = true;
        $_POST['transform_fields_list'] = '0%5Bvc%5D=sss%20s%20s&1%5Bvc%5D=zzff%20s%20sf%0A';
        $_POST['relational_display'] = 'K';
        $_POST['goto'] = 'index.php?route=/sql';
        $_POST['submit_type'] = 'save';
        $_POST['fields'] = ['multi_edit' => [0 => ['zzff s sf'], 1 => ['sss s s']]];
        $_POST['fields_name'] = ['multi_edit' => [0 => ['vc'], 1 => ['vc']]];
        $_POST['fields_null'] = ['multi_edit' => [0 => [], 1 => []]];

        $request = $this->createStub(ServerRequest::class);
        $request->method('getParsedBodyParam')->willReturnMap([['sql_query', null, '']]);

        $dummyDbi = $this->createDbiDummy();
        $dbi = $this->createDatabaseInterface($dummyDbi);
        $relation = new Relation($dbi);
        $transformations = new Transformations();
        $template = new Template();
        $response = new ResponseRenderer();
        $replaceController = new ReplaceController(
            $response,
            $template,
            new InsertEdit($dbi, $relation, $transformations, new FileListing(), $template),
            $transformations,
            $relation,
            $dbi,
        );

        $sqlController = new SqlController(
            $response,
            $template,
            new Sql(
                $dbi,
                $relation,
                new RelationCleanup($dbi, $relation),
                new Operations($dbi, $relation),
                $transformations,
                $template,
            ),
            new CheckUserPrivileges($dbi),
            $dbi,
        );
        $GLOBALS['containerBuilder'] = $this->createStub(ContainerBuilder::class);
        $GLOBALS['containerBuilder']->method('get')->willReturn($sqlController);

        $GLOBALS['goto'] = 'index.php?route=/sql';
        $dummyDbi->addSelectDb('my_db');
        $dummyDbi->addSelectDb('my_db');
        $replaceController($request);
        $output = $response->getHTMLResult();
        $this->dummyDbi->assertAllSelectsConsumed();
        $this->assertStringContainsString(
            'class="icon ic_s_success"> Showing rows 0 -  1 (2 total, Query took',
            $output,
        );
        $this->assertStringContainsString('SELECT * FROM `test_tbl`', $output);
    }

    public function testIsInsertRow(): void
    {
        $GLOBALS['urlParams'] = [];
        $GLOBALS['goto'] = 'index.php?route=/sql';
        $_POST['insert_rows'] = 5;
        $_POST['sql_query'] = 'SELECT 1';
        $GLOBALS['cfg']['InsertRows'] = 2;
        $GLOBALS['cfg']['Server']['host'] = 'host.tld';
        $GLOBALS['cfg']['Server']['verbose'] = '';

        $dummyDbi = $this->createDbiDummy();
        $dbi = $this->createDatabaseInterface($dummyDbi);
        $GLOBALS['dbi'] = $dbi;
        $relation = new Relation($dbi);
        $transformations = new Transformations();
        $template = new Template();
        $response = new ResponseRenderer();
        $insertEdit = new InsertEdit($dbi, $relation, $transformations, new FileListing(), $template);
        $replaceController = new ReplaceController(
            $response,
            $template,
            $insertEdit,
            $transformations,
            $relation,
            $dbi,
        );

        $request = $this->createStub(ServerRequest::class);
        $changeController = new ChangeController($response, $template, $insertEdit, $relation);
        $GLOBALS['containerBuilder'] = $this->createStub(ContainerBuilder::class);
        $GLOBALS['containerBuilder']->method('get')->willReturn($changeController);

        $dummyDbi->addSelectDb('my_db');
        $dummyDbi->addSelectDb('my_db');
        $dummyDbi->addResult('SHOW TABLES LIKE \'test_tbl\';', [['test_tbl']]);
        $dummyDbi->addResult('SELECT * FROM `my_db`.`test_tbl` LIMIT 1;', []);
        $dummyDbi->addSelectDb('my_db');

        $replaceController($request);
        $output = $response->getHTMLResult();
        $this->dummyDbi->assertAllSelectsConsumed();
        $this->assertEquals(5, $GLOBALS['cfg']['InsertRows']);
        $this->assertStringContainsString(
            '<form id="continueForm" method="post" '
            . 'action="index.php?route=/table/replace&lang=en" name="continueForm">',
            $output,
        );
        $this->assertStringContainsString(
            'Continue insertion with         <input type="number" '
            . 'name="insert_rows" id="insert_rows" value="5" min="1">',
            $output,
        );
    }

    /**
     * Test for getParamsForUpdateOrInsert
     */
    public function testGetParamsForUpdateOrInsert(): void
    {
        $_POST['where_clause'] = 'LIMIT 1';
        $_POST['submit_type'] = 'showinsert';

        $replaceController = new ReplaceController(
            $this->createStub(ResponseRenderer::class),
            $this->createStub(Template::class),
            $this->createStub(InsertEdit::class),
            $this->createStub(Transformations::class),
            $this->createStub(Relation::class),
            $this->createStub(DatabaseInterface::class),
        );

        /** @var array $result */
        $result = $this->callFunction(
            $replaceController,
            ReplaceController::class,
            'getParamsForUpdateOrInsert',
            [],
        );

        $this->assertEquals(
            [['LIMIT 1'], true, true],
            $result,
        );

        // case 2 (else)
        unset($_POST['where_clause']);
        $_POST['fields']['multi_edit'] = ['a' => 'b', 'c' => 'd'];

        /** @var array $result */
        $result = $this->callFunction(
            $replaceController,
            ReplaceController::class,
            'getParamsForUpdateOrInsert',
            [],
        );

        $this->assertEquals(
            [['a', 'c'], false, true],
            $result,
        );
    }
}
