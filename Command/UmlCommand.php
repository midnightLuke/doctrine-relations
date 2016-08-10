<?php

namespace MidnightLuke\DoctrineUmlBundle\Command;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Fhaculty\Graph\Exception\OutOfBoundsException;
use Fhaculty\Graph\Graph;
use Graphp\GraphViz\GraphViz;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UmlCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('entity:relations:graph')
            ->setDescription('Shows a graph for entity relations using graphviz.')
            ->addArgument(
                'class',
                InputArgument::OPTIONAL,
                'Uses the specified class as the root entity.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $graph = new Graph();
        $graph->setAttribute('graphviz.rankdir', 'LR');
        $em = $this->getContainer()->get('doctrine.orm.default_entity_manager');
        $twig = $this->getContainer()->get('twig');

        $class = $input->getArgument('class');
        if ($class) {
            $classMeta = $em->getMetadataFactory()->getMetadataFor($class);

            $root = $graph->createVertex($classMeta->getName());
            $root->setAttribute('graphviz.shape', 'record');
            $root->setAttribute('graphviz.group', substr($classMeta->getName(), 0, strrpos($classMeta->getName(), '\\')));
        }
        $metadata = $em->getMetadataFactory()->getAllMetadata();

        foreach($metadata as $classMeta) {
            try {
                $vertex = $graph->getVertex($classMeta->getName());
            } catch (OutOfBoundsException $e) {
                $vertex = $graph->createVertex($classMeta->getName());
                $vertex->setAttribute('graphviz.shape', 'record');
                $vertex->setAttribute('graphviz.group', substr($classMeta->getName(), 0, strrpos($classMeta->getName(), '\\')));
            }

            foreach ($classMeta->associationMappings as $field => $association) {
                try {
                    $target = $graph->getVertex($association['targetEntity']);
                } catch (OutOfBoundsException $e) {
                    $target = $graph->createVertex($association['targetEntity']);
                    $target->setAttribute('graphviz.group', substr($association['targetEntity'], 0, strripos($association['targetEntity'], '\\')));
                    $target->setAttribute('graphviz.shape', 'record');
                }
                if (!$vertex->hasEdgeTo($target)) {
                    $edge = $vertex->createEdgeTo($target);
                    switch($association['type']) {
                        case ClassMetadataInfo::ONE_TO_ONE:
                            $edge->setAttribute('graphviz.arrowhead', 'normal');
                            break;
                        case ClassMetadataInfo::MANY_TO_ONE:
                            $edge->setAttribute('graphviz.arrowhead', 'normal');
                            break;
                        case ClassMetadataInfo::ONE_TO_MANY:
                            $edge->setAttribute('graphviz.arrowhead', 'diamond');
                            break;
                        case ClassMetadataInfo::MANY_TO_MANY:
                            $edge->setAttribute('graphviz.arrowhead', 'diamond');
                            break;
                    }
                }
            }
        }

        // Remove vertices not attached to the root.
        if (isset($root)) {
            foreach ($graph->getVertices() as $vertex) {
                if (!$vertex->hasEdgeTo($root)
                    && !$vertex->hasEdgeFrom($root)
                    && $vertex->getId() !== $class) {

                    foreach ($vertex->getEdges() as $edge) {
                        $edge->destroy();
                    }
                    $graph->removeVertex($vertex);
                }
            }
        }
        $graphviz = new GraphViz();
        $graphviz->display($graph);
    }
}
