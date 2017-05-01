<?php

namespace Padawan\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Padawan\Framework\Application\Socket\HttpOutput;
use Padawan\Domain\ProjectRepository;
use Padawan\Domain\Project;
use Padawan\Domain\Project\File;
use Padawan\Parser\Parser;
use Padawan\Parser\Walker\IndexGeneratingWalker;
use Padawan\Domain\Generator\IndexGenerator;
use Padawan\Parser\Walker\ScopeWalker;
use Padawan\Domain\Project\Node\ClassData;
use Padawan\Domain\Project\Index;
use Padawan\Domain\Scope;
use Padawan\Domain\Project\Node\InterfaceData;
use Padawan\Domain\Scope\AbstractChildScope;
use Padawan\Domain\Scope\ClassScope;
use Padawan\Framework\File\ContentProcessor;

class NavigateCommand extends AsyncCommand
{
    const NAVIGATION_TYPE_PARENTS = 'find-parents';
    const NAVIGATION_TYPE_IMPLEMENTATIONS = 'find-implementations';

    protected function configure()
    {
        $this->setName('navigate')
            ->setDescription('Traverses the imlementation up/down')
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'Path to the project root'
            )->addArgument(
                'column',
                InputArgument::REQUIRED,
                'Column number of cursor position'
            )->addArgument(
                'line',
                InputArgument::REQUIRED,
                'Line number of cursor position'
            )->addArgument(
                'data',
                InputArgument::REQUIRED,
                'File contents'
            )->addArgument(
                'filepath',
                InputArgument::REQUIRED,
                'Path to file relative to project root'
            )->addArgument(
                'navigationtype',
                InputArgument::REQUIRED,
                'What information to receive'
            );
    }

    protected function executeAsync(InputInterface $input, HttpOutput $output)
    {
        $column = $input->getArgument('column');
        $file = $input->getArgument('filepath');
        $line = $input->getArgument('line');
        $content = $input->getArgument('data');
        $path = $input->getArgument('path');
        $navigationType = $input->getArgument('navigationtype');

        $project = $this->getContainer()->get(ProjectRepository::class)->findByPath($path);
        try {
            $result = $this->navigate($project, $content, $line, $column, $file, $navigationType);

            yield $output->write(
                json_encode($result)
            );
            yield $output->disconnect();
        } catch (\Exception $e) {
            yield $output->write(
                json_encode(
                    [
                        'error' => $e->getMessage(),
                    ]
                )
            );
        }
    }

    /**
     * @param Project $project
     * @param string  $content
     * @param int     $line
     * @param int     $column
     * @param string  $file
     *
     * @return array
     */
    private function navigate(
        Project $project,
        $content,
        $line,
        $column,
        $file,
        $navigationType
    ) {
        $scope = $this->processScopeByFileContent($line, $content, $column, $project, $file);
        /* @var $currentClass \Padawan\Domain\Project\Node\ClassData  */
        $currentClass = $this->findCurrentClass($project, $file, $scope);

        switch ($navigationType) {
            case self::NAVIGATION_TYPE_PARENTS:
                return $this->findParents($currentClass);
            case self::NAVIGATION_TYPE_IMPLEMENTATIONS:
                return $this->findImplementations($currentClass, $project->getIndex());
            default:
                throw new \InvalidArgumentException(
                    sprintf(
                        'Given navigation type %s is not defined',
                        $navigationType
                    )
                );
        }
    }

    /**
     * @param ClassData|InterfaceData $currentClass
     * @param Index                   $projectIndex
     *
     * @return array
     */
    private function findImplementations($currentClass, Index $projectIndex)
    {
        $children = [];
        $result = [
            'children' => $children,
        ];

        $items = [];
        if ($currentClass instanceof ClassData) {
            $items = $projectIndex->findClassChildren($currentClass->fqcn);
        } elseif ($currentClass instanceof InterfaceData) {
            $items = $projectIndex->findInterfaceChildrenClasses($currentClass->fqcn);
        }

        foreach ($items as $item) {
            $result['children'][] = [
                'name' => $item->fqcn->getClassName(),
                'fqcn' => $item->fqcn->toString(),
                'file' => $item->file,
            ];
        }

        return $result;
    }

    /**
     * @param ClassData|InterfaceData $currentClass
     *
     * @return array
     */
    private function findParents($currentClass)
    {
        $parent = [];
        $result = [
            'parents' => $parent,
        ];
        if (!$currentClass instanceof ClassData) {
            return $result;
        }

        $parents = [];
        if ($currentClass->getParent()) {
            $parents[] = $currentClass->getParent();
        }
        if ($currentClass->getInterfaces()) {
            foreach ($currentClass->getInterfaces() as $interface) {
                $parents[] = $interface;
            }
        }
        foreach ($parents as $parent) {
            $result['parents'][] = [
                'name' => $parent->fqcn->getClassName(),
                'fqcn' => $parent->fqcn->toString(),
                'file' => $parent->file,
            ];
        }

        return $result;
    }

    /**
     * @param int     $line
     * @param string  $content
     * @param int     $column
     * @param Project $project
     * @param string  $file
     *
     * @return Scope|null
     */
    private function processScopeByFileContent($line, $content, $column, Project $project, $file)
    {
        return $this->getContainer()->get(ContentProcessor::class)->processFileContent($project, $content, $line, $file);
    }

    /**
     * @param Project $project
     * @param string  $path
     * @param Scope   $scope
     *
     * @return ClassData|null
     */
    protected function findCurrentClass(Project $project, $path, Scope $scope)
    {
        $class = $this->extractClassFromScope($scope);
        if ($class) {
            return $class;
        }

        $index = $project->getIndex();
        /** @var File $file */
        $file = $index->findFileByPath($path);
        if (!$file) {
            return;
        }
        $interface = $this->extractInterfaceFromFileScope($file);
        if ($interface) {
            return $interface;
        }

        $fqcn = $index->findFQCNByFile($file->path());
        if (!$fqcn) {
            return;
        }
        $class = $index->findClassByFQCN($fqcn);

        return $class;
    }

    private function extractInterfaceFromFileScope(File $file)
    {
        if ($file->scope()
            && empty($file->scope()->getClasses())
            && count($file->scope()->getInterfaces()) === 1
        ) {
            $interfaces = $file->scope()->getInterfaces();

            return array_pop($interfaces);
        }
    }

    private function extractClassFromScope($scope)
    {
        if ($scope instanceof ClassScope) {
            return $scope->getClass();
        }
        while ($scope instanceof AbstractChildScope && $scope->getParent()) {
            $scope = $scope->getParent();
            if ($scope instanceof ClassScope) {
                return $scope->getClass();
            }
        }
    }

    /**
     * @param string $content
     * @param int    $line
     * @param string $column
     *
     * @return array
     */
    protected function prepareContent($content, $line, $column)
    {
        $lines = explode("\n", $content);
        if ($line > count($lines)) {
            $badLine = '';
        } else {
            $badLine = $lines[$line - 1];
        }
        $completionLine = substr($badLine, 0, $column - 1);
        $lines[$line - 1] = '';

        return [$lines, trim($badLine), trim($completionLine)];
    }

    /** @var Parser */
    private $parser;
    /** @property IndexGenerator */
    private $generator;
    private $contextResolver;
    private $completerFactory;
    /** @property IndexGeneratingWalker */
    private $indexGeneratingWalker;
    /** @property ScopeWalker */
    private $scopeWalker;
    /** @var LoggerInterface */
    private $logger;
}
