<?php

namespace Glavweb\RestBundle\Command;

use Glavweb\RestBundle\Generator\ScopeGenerator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class GenerateScopeCommand
 *
 * @author Nilov Andrey <nilov@glavweb.ru>
 * @package Glavweb\RestBundle
 */
class GenerateScopeCommand extends ContainerAwareCommand
{
    /**
     * Configure
     */
    protected function configure()
    {
        $this
            ->setName('glavweb:rest:scope')
            ->setDescription('Generates a scope based on the given model class')
            ->addArgument('model', InputArgument::REQUIRED, 'The fully qualified model class')
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container         = $this->getContainer();
        $modelClass        = $this->validateClass($input->getArgument('model'));
        $doctrine          = $this->getContainer()->get('doctrine');
        $skeletonDirectory = __DIR__ . '/../Resources/skeleton';

        // Fixture file
        try {
            $scopeGenerator = new ScopeGenerator(
                $container->get('kernel'),
                $doctrine,
                $container->getParameter('kernel.root_dir'),
                $skeletonDirectory
            );

            $scopeGenerator->generate($modelClass);
            $output->writeln(sprintf(
                '%sThe scope files "<info>%s</info>" has been generated.',
                PHP_EOL,
                realpath($scopeGenerator->getTemplateFile())
            ));

        } catch (\Exception $e) {
            $this->writeError($output, $e->getMessage());
        }

        return 0;
    }

    /**
     * @param OutputInterface $output
     * @param string $message
     */
    protected function writeError(OutputInterface $output, $message)
    {
        $output->writeln(sprintf("\n<error>%s</error>", $message));
    }

    /**
     *
     * @param string $class
     * @return string
     * @throws \InvalidArgumentException
     */
    public function validateClass($class)
    {
        $class = str_replace('/', '\\', $class);

        if (!class_exists($class)) {
            throw new \InvalidArgumentException(sprintf('The class "%s" does not exist.', $class));
        }

        return $class;
    }
}
