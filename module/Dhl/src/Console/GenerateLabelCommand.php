<?php

declare(strict_types=1);

namespace App\Console;

use App\Service\ShipmentServiceDHL;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Commande CLI pour créer une expédition DHL et générer l'étiquette PDF.
 * Utilisation : php dhl.php --account=default --input=shipment.json
 */
final class GenerateLabelCommand extends Command
{
    protected static $defaultName = 'shipment:create';

    /**
     * @param ShipmentServiceDHL $shipmentService Service de création d'expéditions DHL
     */
    public function __construct(private readonly ShipmentServiceDHL $shipmentService)
    {
        parent::__construct();
    }

    /**
     * Configure les options de la commande CLI.
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Create a DHL Express shipment and download the label.')
            ->addOption('account', 'a', InputOption::VALUE_REQUIRED, 'Account key defined in config/accounts.php', 'default')
            ->addOption('input', 'i', InputOption::VALUE_REQUIRED, 'Path to the JSON payload describing the shipment', 'shipment.json')
            ->addOption('output-dir', 'o', InputOption::VALUE_REQUIRED, 'Directory where the PDF label will be saved', 'labels')
            ->addOption('label-format', null, InputOption::VALUE_OPTIONAL, 'Override label format (A4 or A6)')
            ->addOption('service-code', null, InputOption::VALUE_OPTIONAL, 'Override DHL service code (P, D, E, etc.)')
            ->addOption('check-products', null, InputOption::VALUE_NONE, 'Check available products before creating shipment')
            ->addOption('check-rates', null, InputOption::VALUE_NONE, 'Check rates before creating shipment')
            ->addOption('select-product', null, InputOption::VALUE_NONE, 'Interactively select a product from available products')
            ->addOption('debug', null, InputOption::VALUE_NONE, 'Show full API response on error');
    }

    /**
     * Exécute la commande : crée l'expédition DHL et sauvegarde l'étiquette PDF.
     *
     * @param InputInterface $input Interface d'entrée pour les options
     * @param OutputInterface $output Interface de sortie pour les messages
     * @return int Code de retour (Command::SUCCESS ou Command::FAILURE)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Récupérer les options de la commande
        $account = (string) $input->getOption('account');
        $inputFile = (string) $input->getOption('input');
        $outputDir = rtrim((string) $input->getOption('output-dir'), DIRECTORY_SEPARATOR);
        $checkProducts = $input->getOption('check-products');
        $checkRates = $input->getOption('check-rates');
        $selectProduct = $input->getOption('select-product');

        $selectedProductCode = null;

        // Vérifier les produits disponibles si demandé ou si sélection interactive
        if ($checkProducts || $selectProduct) {
            try {
                $output->writeln('<info>Checking available products...</info>');
                $products = $this->shipmentService->getAvailableProductsFromFile($account, $inputFile);
                $this->displayProducts($output, $products);
                $output->writeln('');

                // Si sélection interactive, proposer de choisir un produit
                if ($selectProduct && isset($products['products']) && is_array($products['products']) && count($products['products']) > 0) {
                    $selectedProductCode = $this->selectProductInteractively($input, $output, $products['products']);
                    if ($selectedProductCode === null) {
                        $output->writeln('<comment>No product selected. Using default or specified service code.</comment>');
                    } else {
                        $output->writeln(sprintf('<info>Selected product: <comment>%s</comment></info>', $selectedProductCode));
                    }
                }
            } catch (InvalidArgumentException|RuntimeException $exception) {
                $output->writeln('<error>Failed to check products: ' . $exception->getMessage() . '</error>');
                return Command::FAILURE;
            }
        }

        // Vérifier les tarifs si demandé
        if ($checkRates) {
            try {
                $output->writeln('<info>Checking rates...</info>');
                $rates = $this->shipmentService->getRatesFromFile($account, $inputFile);
                $this->displayRates($output, $rates);
                $output->writeln('');
            } catch (InvalidArgumentException|RuntimeException $exception) {
                $output->writeln('<error>Failed to check rates: ' . $exception->getMessage() . '</error>');
                return Command::FAILURE;
            }
        }

        // Si seulement vérification (pas de création de shipment)
        if (($checkProducts || $checkRates) && !$selectProduct) {
            $output->writeln('<comment>Note: Use without --check-products or --check-rates to create the shipment, or use --select-product to choose a product.</comment>');
            return Command::SUCCESS;
        }

        // Préparer les surcharges (serviceCode, labelFormat)
        // Priorité : produit sélectionné > option --service-code > JSON > défaut
        $serviceCodeOverride = $selectedProductCode ?? ($input->getOption('service-code') ?: null);
        $overrides = array_filter([
            'serviceCode' => $serviceCodeOverride,
            'labelFormat' => $input->getOption('label-format') ?: null,
        ]);

        // Créer l'expédition DHL
        $debugMode = $input->getOption('debug');
        try {
            $result = $this->shipmentService->createShipmentFromFile($account, $inputFile, $overrides);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            
            // En mode debug, afficher la réponse complète si disponible
            if ($debugMode && isset($result['response'])) {
                $output->writeln('');
                $output->writeln('<comment>Full API Response:</comment>');
                $output->writeln(json_encode($result['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            
            return Command::FAILURE;
        }

        // Extraire le tracking number et le contenu du label (base64)
        $tracking = $result['trackingNumber'];
        $labelContent = $result['labelContent'];

        // Décoder le contenu base64 en binaire PDF
        $pdfBinary = base64_decode($labelContent, true);
        if ($pdfBinary === false) {
            $output->writeln('<error>Unable to decode label content (expected base64 string).</error>');
            return Command::FAILURE;
        }

        // Créer le dossier de sortie s'il n'existe pas
        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
            $output->writeln(sprintf('<error>Unable to create output directory "%s".</error>', $outputDir));
            return Command::FAILURE;
        }

        // Sauvegarder le PDF avec le tracking number comme nom de fichier
        $pdfPath = $outputDir . DIRECTORY_SEPARATOR . $tracking . '.pdf';
        file_put_contents($pdfPath, $pdfBinary);

        // Afficher le résultat
        $output->writeln('<info>✓ DHL label generated successfully</info>');
        $output->writeln(sprintf('Tracking Number : <comment>%s</comment>', $tracking));
        $output->writeln(sprintf('Label Path      : <comment>%s</comment>', $pdfPath));

        return Command::SUCCESS;
    }

    /**
     * Affiche les produits disponibles.
     *
     * @param OutputInterface $output Interface de sortie
     * @param array<string, mixed> $products Réponse de l'API contenant les produits
     */
    private function displayProducts(OutputInterface $output, array $products): void
    {
        if (!isset($products['products']) || !is_array($products['products'])) {
            $output->writeln('<comment>No products available.</comment>');
            return;
        }

        $productList = $products['products'];
        $count = count($productList);

        $output->writeln(sprintf('<info>✓ Found %d available product(s):</info>', $count));
        $output->writeln('');

        foreach ($productList as $index => $product) {
            $num = $index + 1;
            $output->writeln(sprintf('  [%d] <comment>%s</comment>', $num, $product['productCode'] ?? 'N/A'));
            
            if (isset($product['productName'])) {
                $output->writeln(sprintf('      Name: %s', $product['productName']));
            }
            
            if (isset($product['productTypeCode'])) {
                $output->writeln(sprintf('      Type: %s', $product['productTypeCode']));
            }
            
            if (isset($product['deliveryDate'])) {
                $output->writeln(sprintf('      Estimated Delivery: %s', $product['deliveryDate']));
            }
            
            $output->writeln('');
        }

        $output->writeln('<comment>Tip: Use one of these product codes in your shipment request.</comment>');
    }

    /**
     * Affiche les tarifs disponibles.
     *
     * @param OutputInterface $output Interface de sortie
     * @param array<string, mixed> $rates Réponse de l'API contenant les tarifs
     */
    private function displayRates(OutputInterface $output, array $rates): void
    {
        if (!isset($rates['products']) || !is_array($rates['products'])) {
            $output->writeln('<comment>No rates available.</comment>');
            return;
        }

        $productList = $rates['products'];
        $count = count($productList);

        $output->writeln(sprintf('<info>✓ Found %d rate(s):</info>', $count));
        $output->writeln('');

        foreach ($productList as $index => $product) {
            $num = $index + 1;
            $output->writeln(sprintf('  [%d] <comment>%s</comment>', $num, $product['productCode'] ?? 'N/A'));
            
            if (isset($product['productName'])) {
                $output->writeln(sprintf('      Name: %s', $product['productName']));
            }
            
            // Afficher le prix si disponible
            if (isset($product['totalPrice'])) {
                $currency = $product['currencyCode'] ?? 'EUR';
                $price = is_array($product['totalPrice']) 
                    ? ($product['totalPrice'][0]['price'] ?? 'N/A')
                    : $product['totalPrice'];
                $output->writeln(sprintf('      Price: <info>%s %s</info>', $price, $currency));
            }
            
            if (isset($product['deliveryDate'])) {
                $output->writeln(sprintf('      Estimated Delivery: %s', $product['deliveryDate']));
            }
            
            $output->writeln('');
        }
    }

    /**
     * Permet de sélectionner interactivement un produit parmi ceux disponibles.
     *
     * @param InputInterface $input Interface d'entrée
     * @param OutputInterface $output Interface de sortie
     * @param array<int, array<string, mixed>> $products Liste des produits disponibles
     * @return string|null Code produit sélectionné ou null si annulé
     */
    private function selectProductInteractively(InputInterface $input, OutputInterface $output, array $products): ?string
    {
        if (!$input->isInteractive()) {
            $output->writeln('<comment>Not in interactive mode. Skipping product selection.</comment>');
            return null;
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        
        // Construire les choix
        $choices = [];
        $productMap = [];
        
        foreach ($products as $index => $product) {
            $productCode = $product['productCode'] ?? 'N/A';
            $productName = $product['productName'] ?? 'Unknown';
            $num = $index + 1;
            
            $label = sprintf('[%d] %s - %s', $num, $productCode, $productName);
            
            // Ajouter le prix si disponible
            if (isset($product['totalPrice'])) {
                $currency = $product['currencyCode'] ?? 'EUR';
                $price = is_array($product['totalPrice']) 
                    ? ($product['totalPrice'][0]['price'] ?? 'N/A')
                    : $product['totalPrice'];
                $label .= sprintf(' (%s %s)', $price, $currency);
            }
            
            $choices[] = $label;
            $productMap[$num] = $productCode;
        }
        
        // Ajouter une option pour annuler
        $choices[] = 'Cancel (use default or specified service code)';
        $productMap[count($products) + 1] = null;

        $question = new ChoiceQuestion(
            '<question>Select a product to use for this shipment:</question>',
            $choices,
            count($products) + 1 // Par défaut, annuler
        );

        $question->setErrorMessage('Invalid selection: %s');

        try {
            $selectedIndex = $helper->ask($input, $output, $question);
            
            // Extraire le numéro de la sélection
            if (preg_match('/\[(\d+)\]/', $selectedIndex, $matches)) {
                $selectedNum = (int) $matches[1];
                return $productMap[$selectedNum] ?? null;
            }
            
            // Si "Cancel" sélectionné
            return null;
        } catch (\Exception $e) {
            $output->writeln('<comment>Product selection cancelled.</comment>');
            return null;
        }
    }
}

