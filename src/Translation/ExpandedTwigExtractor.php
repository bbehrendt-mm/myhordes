<?php


namespace App\Translation;

use App\Service\Globals\TranslationConfigGlobal;
use Iterator;
use Symfony\Bridge\Twig\Translation\TwigExtractor;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\Extractor\PhpStringTokenParser;
use Symfony\Component\Translation\MessageCatalogue;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Source;

class ExpandedTwigExtractor extends TwigExtractor
{
    private TranslationConfigGlobal $config;
    private Environment $environment;
    private KernelInterface $kernel;

    /**
     * Prefix for found message.
     *
     * @var string
     */
    private $prefix = '';
    private $defaultDomain = 'messages';
    private $file = '';

    /**
     * {@inheritdoc}
     */
    public function setPrefix(string $prefix)
    {
        $this->prefix = $prefix;
    }

    public function __construct(Environment $twig, TranslationConfigGlobal $config, KernelInterface $kernel)
    {
        parent::__construct($twig);
        $this->environment = $twig;
        $this->config = $config;
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    public function extract($resource, MessageCatalogue $catalogue)
    {
        foreach ($this->extractFiles($resource) as $file) {
            try {
                $this->file = $file->getPathname();
                $this->extractTemplate(file_get_contents($file->getPathname()), $catalogue);
            } catch (Error $e) {
                // ignore errors, these should be fixed by using the linter
            }
        }
    }

    /**
     * @return bool
     *
     * @throws \InvalidArgumentException
     */
    protected function canBeExtracted(string $file): bool
    {
        if (!$this->config->useTwig() || !parent::canBeExtracted($file)) return false;
        return !$this->config->useFileNameMatching() || in_array(basename($file),$this->config->matchingFileNames());
    }

    protected function extractTemplate(string $template, MessageCatalogue $catalogue)
    {
        $visitor = $this->environment->getExtension('Symfony\Bridge\Twig\Extension\TranslationExtension')->getTranslationNodeVisitor();
        $visitor->enable();

        $this->environment->parse($this->environment->tokenize(new Source($template, '')));

        foreach ($visitor->getMessages() as $message) {
            $catalogue->set(trim($message[0]), $this->prefix.trim($message[0]), $message[1] ?: $this->defaultDomain);
            $this->config->add_source_for(trim($message[0]), $message[1] ?: $this->defaultDomain, 'twig', str_replace($this->kernel->getProjectDir(),'',$this->file));
        }

        $visitor->disable();
    }

}