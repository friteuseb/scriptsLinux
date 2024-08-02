<?php

class TYPO3ExtensionReport {
    private $extensionPath;
    private $report = [];

    public function __construct($extensionPath) {
        $this->extensionPath = $extensionPath;
    }

    public function generateReport() {
        if (!is_dir($this->extensionPath)) {
            echo "Le répertoire spécifié n'existe pas : {$this->extensionPath}\n";
            return;
        }

        $this->collectComposerInfo();
        $this->analyzeDirectory($this->extensionPath);
        $this->saveReport();
    }

    private function collectComposerInfo() {
        $composerFile = $this->extensionPath . DIRECTORY_SEPARATOR . 'composer.json';

        if (file_exists($composerFile)) {
            $composerData = json_decode(file_get_contents($composerFile), true);

            $this->report['composer'] = [
                'name' => $composerData['name'] ?? '',
                'description' => $composerData['description'] ?? '',
                'version' => $composerData['version'] ?? '',
                'autoload' => $composerData['autoload'] ?? [],
                'psr-4' => $composerData['autoload']['psr-4'] ?? []
            ];
        } else {
            $this->report['composer'] = 'Fichier composer.json non trouvé';
        }
    }

    private function analyzeDirectory($directory) {
        $files = scandir($directory);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            // Exclude the .git directory
            if ($file === '.git') {
                continue;
            }

            $filePath = $directory . DIRECTORY_SEPARATOR . $file;

            if (is_dir($filePath)) {
                $this->analyzeDirectory($filePath);
            } else {
                $this->analyzeFile($filePath);
            }
        }
    }

    private function analyzeFile($filePath) {
        $fileInfo = pathinfo($filePath);
        $fileType = $fileInfo['extension'] ?? '';

        switch ($fileType) {
            case 'php':
                $this->analyzePHPFile($filePath);
                break;
            case 'ts':
            case 'typoscript':
                $this->report['typoscript_files'][] = $filePath;
                break;
            case 'html':
            case 'fluid':
                $this->report['template_files'][] = $filePath;
                break;
            case 'yaml':
            case 'yml':
                $this->report['configuration_files'][] = $filePath;
                break;
            default:
                $this->report['other_files'][] = $filePath;
                break;
        }
    }

    private function analyzePHPFile($filePath) {
        $fileContent = file_get_contents($filePath);
        $tokens = token_get_all($fileContent);
        $namespace = '';
        $class = '';
        $functions = [];

        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                $namespace = $this->collectNamespace($tokens, $i);
            }

            if ($tokens[$i][0] === T_CLASS) {
                $class = $this->collectClass($tokens, $i);
            }

            if ($tokens[$i][0] === T_FUNCTION) {
                $functions[] = $this->collectFunction($tokens, $i);
            }
        }

        $this->report['php_files'][] = [
            'path' => $filePath,
            'namespace' => $namespace,
            'class' => $class,
            'functions' => $functions
        ];
    }

    private function collectNamespace($tokens, &$index) {
        $namespace = '';
        $index += 2; // skip 'namespace' keyword and whitespace
        while (isset($tokens[$index]) && ($tokens[$index][0] === T_STRING || $tokens[$index] === '\\')) {
            $namespace .= is_array($tokens[$index]) ? $tokens[$index][1] : $tokens[$index];
            $index++;
        }
        return $namespace;
    }

    private function collectClass($tokens, &$index) {
        $index += 2; // skip 'class' keyword and whitespace
        return isset($tokens[$index][1]) ? $tokens[$index][1] : '';
    }

    private function collectFunction($tokens, &$index) {
        $index += 2; // skip 'function' keyword and whitespace
        return isset($tokens[$index][1]) ? $tokens[$index][1] : '';
    }

    private function saveReport() {
        $reportPath = $this->extensionPath . DIRECTORY_SEPARATOR . 'extension_report.json';
        file_put_contents($reportPath, json_encode($this->report, JSON_PRETTY_PRINT));
        echo "Report generated at: $reportPath\n";
    }
}

// Prompt the user for the path of the extension
echo "Entrez le chemin du répertoire de l'extension TYPO3 : ";
$handle = fopen("php://stdin", "r");
$extensionPath = trim(fgets($handle));

$reportGenerator = new TYPO3ExtensionReport($extensionPath);
$reportGenerator->generateReport();

?>
