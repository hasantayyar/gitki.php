<?php


namespace Net\Dontdrinkandroot\Gitki\ElasticSearchBundle\Command;


use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReindexCommand extends ElasticSearchCommand
{

    protected function configure()
    {
        $this->setName('gitki:reindex')
            ->setDescription('Reindex all Markdown documents');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $wikiService = $this->getWikiService();
        $elasticSearchRepo = $this->getElasticSearchRepository();

        $elasticSearchRepo->deleteMarkdownDocumentIndex();

        $filePaths = $wikiService->findAllMarkdownFiles();

        $progress = new ProgressBar($output, count($filePaths));
        $progress->start();

        foreach ($filePaths as $filePath) {
            $progress->setMessage('Indexing ' . $filePath->toUrlString());
            $progress->advance();
            $content = $wikiService->getContent($filePath);
            $elasticSearchRepo->indexMarkdownDocument($filePath, $content);
        }

        $progress->finish();

        $output->writeln('');
    }

} 