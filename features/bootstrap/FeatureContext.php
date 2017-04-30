<?php

use Mkusher\Co;
use Fake\Output;
use DI\Container;
use Padawan\Domain\Scope;
use Padawan\Domain\Project;
use Psr\Log\LoggerInterface;
use React\EventLoop\Factory;
use Monolog\Handler\NullHandler;
use Behat\Behat\Context\Context;
use Padawan\Domain\Project\File;
use Padawan\Domain\Project\Index;
use Behat\Gherkin\Node\TableNode;
use Padawan\Domain\Scope\FileScope;
use Behat\Gherkin\Node\PyStringNode;
use Padawan\Framework\Application\Socket;
use Padawan\Framework\Generator\IndexGenerator;
use Behat\Behat\Context\SnippetAcceptingContext;
use Padawan\Framework\Domain\Project\InMemoryIndex;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context, SnippetAcceptingContext
{
    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
        $this->createApplication();
        $this->createProject();
    }

    public function createApplication()
    {
        $this->loop = Factory::create();
        $this->app = new Socket($this->loop);
        $container = $this->app->getContainer();
        $container->get(LoggerInterface::class)->popHandler();
        $container->get(LoggerInterface::class)->pushHandler(new NullHandler());
    }

    public function createProject()
    {
        $this->project = new Project(new InMemoryIndex);
    }

    /**
     * @Given there is a file with:
     */
    public function thereIsAFileWith(PyStringNode $string)
    {
        $filePath = uniqid() . ".php";
        $file = new File($filePath);
        $container = $this->app->getContainer();
        $generator = $container->get(IndexGenerator::class);
        $walker = $generator->getWalker();
        $parser = $generator->getClassUtils()->getParser();
        $parser->addWalker($walker);
        $parser->setIndex($this->project->getIndex());
        $this->content = $string->getRaw();
        $scope = $parser->parseContent($filePath, $this->content, null, false);
        $generator->processFileScope($file, $this->project->getIndex(), $scope, sha1($this->content));
        $this->scope = $scope;
    }

    /**
     * @When I type :code on the :line line
     */
    public function iTypeOnTheLine($code, $linenum)
    {
        $content = explode("\n", $this->content);
        if (!isset($content[$linenum-1])) {
            $content[$linenum-1] = "";
        }
        $content[$linenum-1] .= $code;
        $this->content = implode("\n", $content);
        $this->line = $linenum - 1;
        $this->column = strlen($content[$linenum-1]);
    }

    /**
     * @When I ask for completion
     */
    public function askForCompletion()
    {
        $request = $this->buildRequest("complete");
        $this->doRequest($request);
    }

    /**
     * @When I ask for implementations
     */
    public function iAskForImplementations()
    {
        $request = $this->buildRequest("navigate");
        $request->params->navigationtype = 'find-implementations';
        $this->doRequest($request);
    }

    /**
     * @When I ask for the parents of current class
     */
    public function askForParents()
    {
        $request = $this->buildRequest("navigate");
        $request->params->navigationtype = 'find-parents';
        $this->doRequest($request);
    }

    /**
     * @When I ask for definition
     */
    public function asForTheDefinition()
    {
        $request = $this->buildRequest("navigate");
        $request->params->navigationtype = 'go-to-definition';
        $this->doRequest($request);
    }

    /**
     * @When I move my cursor to line :line
     */
    public function iMoveMyCursorToLine($line)
    {
        $this->line = $line - 1;
        $this->column = strlen($this->content[$line-1]);
    }

    /**
     * @When I move my cursor to column :column
     */
    public function iMoveMyCursorToColumn($column)
    {
        $this->column = $column-1;
    }

    /**
     * @Then I should get following :type:
     */
    public function iShouldGetFollowing($type, TableNode $table)
    {
        if (isset($this->response["error"])) {
            throw new \Exception(
                sprintf("Application response contains error: %s", $this->response["error"])
            );
        }

        if (!isset($this->response[$type])) {
            throw new \Exception(
                sprintf(
                    "Expected to find %s as toplevel key but Application response contains only: %s",
                    $type,
                    implode(', ', array_keys($this->response))
                )
            );
        }
        $expected = [];
        if (!empty($table->getColumnsHash())) {
            $expected = $table->getColumnsHash();
        }
        expect($this->response[$type])->to->loosely->equal($expected);
    }

    /**
     * @Then I should get:
     */
    public function iShouldGet(TableNode $table)
    {
        if (isset($this->response["error"])) {
            throw new \Exception(
                sprintf("Application response contains error: %s", $this->response["error"])
            );
        }
        $columns = $table->getRow(0);
        $result = array_map(function ($item) use($columns) {
            $hash = [];
            $map = [
                "Name" => "name",
                "Signature" => "signature",
                "Menu" => "menu"
            ];
            foreach ($columns as $column) {
                $hash[$column] = $item[$map[$column]];
            }
            return $hash;
        }, $this->response["completion"]);
        expect($table->getColumnsHash())->to->loosely->equal($result);
    }

    /**
     * @param \stdclass $request
     */
    private function doRequest($request)
    {
        $output = new Output;
        $app = $this->app;
        Co\await(function() use ($request, $output, $app) {
            yield $app->handle($request, $output);
        })->then(function() use ($output) {
            $this->response = json_decode($output->output[0], 1);
        });
        $this->loop->run();
    }

    /**
     * @param string $command
     * @return \stdclass
     */
    private function buildRequest($command)
    {
        $request = new \stdclass;
        $request->command = $command;
        $request->params = new \stdclass;
        $request->params->line = $this->line + 1;
        $request->params->column = $this->column + 1;
        $request->params->filepath = $this->filename;
        $request->params->path = $this->path;
        $request->params->data = $this->content;

        return $request;
    }

    /** @var App */
    private $app;
    /** @var Project */
    private $project;
    /** @var string */
    private $path;
    /** @var string */
    private $filename;
    /** @var int */
    private $line;
    /** @var int */
    private $column;
    /** @var string */
    private $content;
    /** @var array */
    private $response;
    /** @var Scope */
    private $scope;
}
