<?php

namespace Command;

use League\Csv\Writer;
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
            ->addOption('identity', 'i', InputOption::VALUE_OPTIONAL, 'minimal % identity for match', 30)
            ->addOption('firstBlastName', 'f', InputOption::VALUE_OPTIONAL, 'Name of the first blast', 'First blast')
            ->addOption('secondBlastName', 's', InputOption::VALUE_OPTIONAL, 'Name of the second blast', 'Second blast')
        ;
    }

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

        $matches = $this->compare($firstBlast, $secondBlast);

        if (0 === count($matches)) {
            $output->writeln($formatter->formatBlock('No match', 'error'));

            return;
        }

        $headers = [
            'Reference',
            sprintf('%s identity', $input->getOption('firstBlastName')),
            sprintf('%s accession', $input->getOption('firstBlastName')),
            sprintf('%s identity', $input->getOption('secondBlastName')),
            sprintf('%s accession', $input->getOption('secondBlastName')),
        ];

        $table = new Table($output);
        $table->setHeaders($headers);

        $table->setRows($matches);
        $table->render();

        if ($path = $input->getOption('output')) {
            $csv = Writer::createFromPath($path, "w");
            $csv->insertOne($headers);
            $csv->insertAll(array_values($matches));
            $output->writeln($formatter->formatBlock(sprintf('Output saved in %s', $path), 'info'));
        }
    }

    /**
     * @param string $path
     * @param int    $identity
     *
     * @return array
     */
    private function extract($path, $identity)
    {
        $blast = new SimpleXMLElement(file_get_contents($path));

        $hits = $blast->BlastOutput_iterations->Iteration->Iteration_hits->Hit;

        $extract = [];

        foreach ($hits as $i => $hit) {

            $hsp = $hit->Hit_hsps->Hsp;

            $hsp_identity = round((int) $hsp->{'Hsp_identity'} * 100 / (int) $hsp->{'Hsp_align-len'});

            if ($hsp_identity < $identity) {
                continue;
            }

            preg_match_all('/\[([^\]]+)\]/', $hit->Hit_def, $matches);

            foreach ($matches[1] as $match) {
                $extract[$match] = [
                    'identity' => $hsp_identity,
                    'accession' => $hit->Hit_accession,
                ];
            }
        }

        return $extract;
    }

    private function compare($firstBlast, $secondBlast)
    {
        $firstBlastRefs = array_keys($firstBlast);
        $secondBlastRefs = array_keys($secondBlast);

        $commonRefs = array_intersect($firstBlastRefs, $secondBlastRefs);

        $result = [];

        foreach ($commonRefs as $ref) {
            $result[] = [
                'ref' => $ref,
                'firstBlastIdentity' => $firstBlast[$ref]['identity'],
                'firstBlastAccession' => $firstBlast[$ref]['accession'],
                'secondBlastIdentity' => $secondBlast[$ref]['identity'],
                'secondBlastAccession' => $secondBlast[$ref]['accession'],
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
}
