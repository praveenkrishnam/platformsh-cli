<?php

namespace CommerceGuys\Platform\Cli\Command;

use CommerceGuys\Platform\Cli\Model\Environment;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\StreamOutput;

class ActivityListCommand extends PlatformCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('activity:list')
            ->setAliases(array('activities'))
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Filter activities by type')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit the number of results displayed', 5)
            ->addOption('pipe', null, InputOption::VALUE_NONE, 'Output tab-separated results')
            ->setDescription('Get the most recent activities for an environment');
        $this->addProjectOption()->addEnvironmentOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->validateInput($input, $output)) {
            return 1;
        }

        $client = $this->getPlatformClient($this->environment['endpoint']);
        $environment = new Environment($this->environment, $client);

        $limit = (int) $input->getOption('limit');
        $results = $environment->getActivities($limit, $input->getOption('type'));
        if (!$results) {
            $output->writeln('No activities found');
            return 1;
        }

        // @todo This can be removed when the 'count' parameter is supported.
        /** @var \CommerceGuys\Platform\Cli\Model\Activity[] $results */
        $results = array_slice($results, 0, $limit);

        $headers = array("ID", "Created", "Description", "% Complete", "Result");
        $rows = array();
        foreach ($results as $result) {
            $description = $result->getDescription();
            $description = wordwrap($description, 40);
            $rows[] = array(
              $result->id(),
              $result->getPropertyFormatted('created_at'),
              $description,
              $result->getPropertyFormatted('completion_percent'),
              $result->getPropertyFormatted('result', false),
            );
        }

        if ($output instanceof StreamOutput && ($input->getOption('pipe') || !$this->isTerminal($output))) {
            $stream = $output->getStream();
            array_unshift($rows, $headers);
            foreach ($rows as $row) {
                fputcsv($stream, $row, "\t");
            }
            return 0;
        }

        $output->writeln("Recent activities for the environment <info>" . $this->environment['id'] . "</info>");
        $table = new Table($output);
        $table->setHeaders($headers);
        $table->addRows($rows);
        $table->render();

        return 0;
    }

}