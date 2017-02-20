<?php

namespace Command;

use League\Csv\Writer;
use Model\Hit;
use SimpleXMLElement;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BlastCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('blast')
            ->setDescription('Compare to blast by % of identity')
            ->addArgument('firstBlast', InputArgument::REQUIRED, 'First blast to compare')
            ->addArgument('secondBlast', InputArgument::REQUIRED, 'Second blast to compare')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Where to output the matches (csv)', false)
            ->addOption('preview', 'p', InputOption::VALUE_OPTIONAL, 'Preview the result in the command line', false)
            ->addOption('identity', 'i', InputOption::VALUE_OPTIONAL, 'minimal % identity for match', 30)
            ->addOption('firstBlastName', 'f', InputOption::VALUE_OPTIONAL, 'Name of the first blast', 'First blast')
            ->addOption('secondBlastName', 's', InputOption::VALUE_OPTIONAL, 'Name of the second blast', 'Second blast')
        ;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $formatter = $this->getHelper('formatter');
        $identity = $input->getOption('identity');

        $output->writeln($formatter->formatBlock(
            sprintf('Comparing with at least %d identity', $identity),
            'info'
        ));

        $firstBlast = $this->extract($input->getArgument('firstBlast'), $identity);
        $secondBlast = $this->extract($input->getArgument('secondBlast'), $identity);

        $blasts = $this->compare($firstBlast, $secondBlast);

        if (0 === count($blasts)) {
            $output->writeln($formatter->formatBlock('No common blast', 'error'));

            return;
        }

        $output->writeln($formatter->formatBlock(sprintf('%d matches', count($blasts)), 'info'));

        $this->outputTable($input, $output, $blasts);
        $this->outputFile($input, $output, $blasts);
    }

    /**
     * @param string $path
     * @param int    $identity
     *
     * @return Hit[]
     */
    private function extract($path, $identity)
    {
        $blast = new SimpleXMLElement(file_get_contents($path));

        $hits = $blast->BlastOutput_iterations->Iteration->Iteration_hits->Hit;

        $extract = [];

        foreach ($hits as $i => $hit) {

            $hit = new Hit($hit);

            if ($hit->getIdentity() < $identity) {
                continue;
            }

            preg_match_all('/\[([^\]]+)\]/', $hit->getReference(), $matches);

            foreach ($matches[1] as $match) {

                $hit->setProtein($match);

                $extract[$match] = $hit;
            }

            if ($i == 50) { return $extract; }
        }

        return $extract;
    }

    /**
     * @param Hit[] $firstBlast
     * @param Hit[] $secondBlast
     *
     * @return array
     */
    private function compare($firstBlast, $secondBlast)
    {
        $firstBlastRefs = array_keys($firstBlast);
        $secondBlastRefs = array_keys($secondBlast);

        $commonRefs = array_intersect($firstBlastRefs, $secondBlastRefs);

        $result = [];

        foreach ($commonRefs as $ref) {
            $result[] = [
                'ref' => $ref,
                'firstBlastIdentity' => $firstBlast[$ref]->getIdentity(),
                'firstBlastAccession' => $firstBlast[$ref]->getAccession(),
                'secondBlastIdentity' => $secondBlast[$ref]->getIdentity(),
                'secondBlastAccession' => $secondBlast[$ref]->getAccession(),
            ];
        }

        usort($result, function ($a, $b) {
            $identityA = $a['firstBlastIdentity'];
            $identityB = $b['firstBlastIdentity'];

            if ($identityA == $identityB) {
                return 0;
            }
            return ($identityA < $identityB) ? 1 : -1;
        });

        return $result;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param array           $blasts
     */
    private function outputTable(InputInterface $input, OutputInterface $output, array $blasts)
    {
        if ($input->getOption('preview')) {
            $table = new Table($output);
            $table->setHeaders($this->getHeaders($input));

            $table->setRows($blasts);
            $table->render();
        }
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param array           $blasts
     */
    private function outputFile(InputInterface $input, OutputInterface $output, array $blasts)
    {
        if ($path = $input->getOption('output')) {
            $csv = Writer::createFromPath($path, "w");
            $csv->insertOne($this->getHeaders($input));
            $csv->insertAll(array_values($blasts));
            $output->writeln($this->getHelper('formatter')->formatBlock(sprintf('Output saved in %s', $path), 'info'));
        }
    }

    /**
     * @param InputInterface $input
     *
     * @return array
     */
    private function getHeaders(InputInterface $input)
    {
        return [
            'Reference',
            sprintf('%s identity', $input->getOption('firstBlastName')),
            sprintf('%s accession', $input->getOption('firstBlastName')),
            sprintf('%s identity', $input->getOption('secondBlastName')),
            sprintf('%s accession', $input->getOption('secondBlastName')),
        ];
    }
}
