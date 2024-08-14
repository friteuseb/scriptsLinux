<?php

class TYPO3ExtensionAnalyzer {
    private $extensionPath;
    private $report = [];

    public function __construct($extensionPath) {
        $this->extensionPath = $extensionPath;
    }

    public function analyzeExtension() {
        if (!is_dir($this->extensionPath)) {
            echo "Le répertoire spécifié n'existe pas : {$this->extensionPath}\n";
            return;
        }

        $this->collectComposerInfo();
        $this->analyzeDirectory($this->extensionPath);
        $this->checkCriticalFiles();
        $this->analyzeExtensionStructure();
        $this->analyzeConfigurationFiles();
        $this->analyzeDependencies();
        $this->analyzeDatabase();
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
                'type' => $composerData['type'] ?? '',
                'require' => $composerData['require'] ?? [],
                'autoload' => $composerData['autoload'] ?? [],
                'extra' => $composerData['extra'] ?? []
            ];
        } else {
            $this->report['composer'] = 'Fichier composer.json non trouvé';
        }
    }

    private function analyzeDirectory($directory) {
        $files = scandir($directory);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '.git') {
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
                $this->report['php_files'][] = $this->analyzePHPFile($filePath);
                break;
            case 'ts':
            case 'typoscript':
                $this->report['typoscript_files'][] = $this->analyzeTypoScriptFile($filePath);
                break;
            case 'html':
            case 'fluid':
                $this->report['template_files'][] = $this->analyzeTemplateFile($filePath);
                break;
            case 'yaml':
            case 'yml':
                $this->report['configuration_files'][] = $this->analyzeYAMLFile($filePath);
                break;
            case 'sql':
                $this->report['sql_files'][] = $this->analyzeSQLFile($filePath);
                break;
            case 'xml':
                $this->report['xml_files'][] = $this->analyzeXMLFile($filePath);
                break;
            default:
                $this->report['other_files'][] = $filePath;
                break;
        }
    }

    private function analyzePHPFile($filePath) {
        $fileContent = file_get_contents($filePath);
        $tokens = token_get_all($fileContent);
        $fileInfo = [
            'path' => $filePath,
            'namespace' => '',
            'uses' => [],
            'classes' => [],
            'interfaces' => [],
            'traits' => [],
            'functions' => [],
            'constants' => [],
            'hooks' => [],
            'plugins' => [],
        ];
    
        $currentNamespace = '';
        $currentClass = null;
        $currentFunction = null;
        $bracketLevel = 0;
        $classLevel = 0;
    
        foreach ($tokens as $index => $token) {
            if (is_array($token)) {
                switch ($token[0]) {
                    case T_NAMESPACE:
                        $currentNamespace = $this->getFullNamespace($tokens, $index);
                        $fileInfo['namespace'] = $currentNamespace;
                        break;
                    case T_USE:
                        $fileInfo['uses'][] = $this->getUseStatement($tokens, $index);
                        break;
                    case T_CLASS:
                        if ($this->isActualClassDeclaration($tokens, $index)) {
                            $className = $this->getClassName($tokens, $index);
                            $currentClass = [
                                'name' => $className,
                                'namespace' => $currentNamespace,
                                'methods' => [],
                                'properties' => [],
                                'type' => 'class',
                                'modifiers' => $this->getClassModifiers($tokens, $index),
                            ];
                            $fileInfo['classes'][] = &$currentClass;
                            $classLevel = $bracketLevel + 1;
                        }
                        break;
                    case T_INTERFACE:
                        $interfaceName = $this->getClassName($tokens, $index);
                        $fileInfo['interfaces'][] = [
                            'name' => $interfaceName,
                            'namespace' => $currentNamespace,
                            'methods' => [],
                        ];
                        break;
                    case T_TRAIT:
                        $traitName = $this->getClassName($tokens, $index);
                        $fileInfo['traits'][] = [
                            'name' => $traitName,
                            'namespace' => $currentNamespace,
                            'methods' => [],
                        ];
                        break;
                    case T_FUNCTION:
                        if ($currentClass && $bracketLevel == $classLevel) {
                            $methodName = $this->getFunctionName($tokens, $index);
                            $currentFunction = [
                                'name' => $methodName,
                                'visibility' => $this->getVisibility($tokens, $index),
                                'static' => $this->isStatic($tokens, $index),
                            ];
                            $currentClass['methods'][] = $currentFunction;
                        } elseif ($bracketLevel == 0) {
                            $functionName = $this->getFunctionName($tokens, $index);
                            $fileInfo['functions'][] = [
                                'name' => $functionName,
                                'namespace' => $currentNamespace,
                            ];
                        }
                        break;
                    case T_CONST:
                        $constantName = $this->getConstantName($tokens, $index);
                        $fileInfo['constants'][] = [
                            'name' => $constantName,
                            'namespace' => $currentNamespace,
                        ];
                        break;
                    case T_VARIABLE:
                        if ($currentClass && $bracketLevel == $classLevel) {
                            $propertyName = substr($token[1], 1); // Remove $
                            $currentClass['properties'][] = [
                                'name' => $propertyName,
                                'visibility' => $this->getVisibility($tokens, $index),
                            ];
                        }
                        break;
                    // Ajoutez ici d'autres cas pour hooks, plugins, etc.
                }
            } elseif ($token === '{') {
                $bracketLevel++;
            } elseif ($token === '}') {
                $bracketLevel--;
                if ($bracketLevel < $classLevel) {
                    $currentClass = null;
                    $classLevel = 0;
                }
            }
        }
    
        return $fileInfo;
    }
    
    private function isActualClassDeclaration($tokens, $index) {
        // Vérifier si c'est une véritable déclaration de classe et non une utilisation du mot-clé 'class'
        $nextToken = $this->getNextNonWhitespaceToken($tokens, $index);
        return $nextToken && $nextToken[0] === T_STRING;
    }
    
    private function getNextNonWhitespaceToken($tokens, $start) {
        for ($i = $start + 1; $i < count($tokens); $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] !== T_WHITESPACE) {
                return $tokens[$i];
            }
            if (!is_array($tokens[$i]) && trim($tokens[$i]) !== '') {
                return $tokens[$i];
            }
        }
        return null;
    }
    
    private function getClassModifiers($tokens, $index) {
        $modifiers = [];
        for ($i = $index - 1; $i >= 0; $i--) {
            if (is_array($tokens[$i])) {
                if ($tokens[$i][0] === T_FINAL) {
                    $modifiers[] = 'final';
                } elseif ($tokens[$i][0] === T_ABSTRACT) {
                    $modifiers[] = 'abstract';
                } elseif ($tokens[$i][0] !== T_WHITESPACE) {
                    break;
                }
            } else {
                break;
            }
        }
        return $modifiers;
    }

    private function getFullNamespace($tokens, &$index) {
        $namespace = '';
        $index += 2; // Skip 'namespace' and whitespace
        while (isset($tokens[$index]) && $tokens[$index] !== ';') {
            if (is_array($tokens[$index])) {
                $namespace .= $tokens[$index][1];
            } else {
                $namespace .= $tokens[$index];
            }
            $index++;
        }
        return trim($namespace);
    }

    private function getUseStatement($tokens, &$index) {
        $use = '';
        $index += 2; // Skip 'use' and whitespace
        while (isset($tokens[$index]) && $tokens[$index] !== ';') {
            if (is_array($tokens[$index])) {
                $use .= $tokens[$index][1];
            } else {
                $use .= $tokens[$index];
            }
            $index++;
        }
        return trim($use);
    }
    private function getClassName($tokens, &$index) {
        $index += 2; // Skip 'class' and whitespace
        return isset($tokens[$index]) && is_array($tokens[$index]) ? $tokens[$index][1] : '';
    }

    private function getFunctionName($tokens, &$index) {
        $index += 2; // Skip 'function' and whitespace
        return isset($tokens[$index]) && is_array($tokens[$index]) ? $tokens[$index][1] : '';
    }

    private function getConstantName($tokens, &$index) {
        $index += 2; // Skip 'const' and whitespace
        return isset($tokens[$index]) && is_array($tokens[$index]) ? $tokens[$index][1] : '';
    }

    private function getVisibility($tokens, $index) {
        $visibilityMap = [
            T_PUBLIC => 'public',
            T_PROTECTED => 'protected',
            T_PRIVATE => 'private',
        ];

        for ($i = $index - 1; $i >= 0; $i--) {
            if (isset($visibilityMap[$tokens[$i][0]])) {
                return $visibilityMap[$tokens[$i][0]];
            }
            if ($tokens[$i] === '}' || $tokens[$i][0] === T_FUNCTION) {
                break;
            }
        }
        return 'public'; // Default visibility
    }

    private function isStatic($tokens, $index) {
        for ($i = $index - 1; $i >= 0; $i--) {
            if ($tokens[$i][0] === T_STATIC) {
                return true;
            }
            if ($tokens[$i] === '}' || $tokens[$i][0] === T_FUNCTION) {
                break;
            }
        }
        return false;
    }

    private function extractHookInfo($tokens, $index) {
        // This is a simplified version. You might need to adjust it based on the exact hook registration syntax
        $hookInfo = [
            'type' => 'hook',
            'service' => '',
            'class' => '',
        ];

        // Find the service name (usually the first string after 'addService')
        for ($i = $index + 3; $i < count($tokens); $i++) {
            if ($tokens[$i][0] === T_CONSTANT_ENCAPSED_STRING) {
                $hookInfo['service'] = trim($tokens[$i][1], "'\"");
                break;
            }
        }

        // Find the class name (usually the last string in the addService call)
        for ($i = $index + 3; $i < count($tokens); $i++) {
            if ($tokens[$i] === ')') {
                break;
            }
            if ($tokens[$i][0] === T_STRING || $tokens[$i][0] === T_CONSTANT_ENCAPSED_STRING) {
                $hookInfo['class'] = trim($tokens[$i][1], "'\"");
            }
        }

        return $hookInfo;
    }

    private function extractPluginInfo($tokens, $index) {
        // This is a simplified version. You might need to adjust it based on the exact plugin registration syntax
        $pluginInfo = [
            'type' => 'plugin',
            'extension' => '',
            'name' => '',
            'title' => '',
        ];

        // Extract plugin information
        for ($i = $index + 1; $i < count($tokens); $i++) {
            if ($tokens[$i] === ')') {
                break;
            }
            if ($tokens[$i][0] === T_CONSTANT_ENCAPSED_STRING) {
                if (empty($pluginInfo['extension'])) {
                    $pluginInfo['extension'] = trim($tokens[$i][1], "'\"");
                } elseif (empty($pluginInfo['name'])) {
                    $pluginInfo['name'] = trim($tokens[$i][1], "'\"");
                } elseif (empty($pluginInfo['title'])) {
                    $pluginInfo['title'] = trim($tokens[$i][1], "'\"");
                }
            }
        }

        return $pluginInfo;
    }

    private function analyzeTypoScriptFile($filePath) {
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        $typoscriptInfo = [
            'path' => $filePath,
            'size' => strlen($content),
            'lines' => count($lines),
            'objects' => [],
            'includes' => [],
        ];

        $currentObject = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            if (preg_match('/^([a-zA-Z0-9_\.]+)\s*=/', $line, $matches)) {
                $currentObject = $matches[1];
                $typoscriptInfo['objects'][] = $currentObject;
            } elseif (strpos($line, '<INCLUDE_TYPOSCRIPT:') === 0) {
                $typoscriptInfo['includes'][] = $line;
            }
        }

        return $typoscriptInfo;
    }

    private function analyzeTemplateFile($filePath) {
        $content = file_get_contents($filePath);
        $templateInfo = [
            'path' => $filePath,
            'size' => strlen($content),
            'lines' => substr_count($content, "\n") + 1,
            'viewhelpers' => [],
            'sections' => [],
        ];

        // Extract ViewHelpers
        preg_match_all('/<([a-zA-Z0-9]+):/i', $content, $matches);
        $templateInfo['viewhelpers'] = array_unique($matches[1]);

        // Extract sections
        preg_match_all('/<f:section name="([^"]+)">/i', $content, $matches);
        $templateInfo['sections'] = $matches[1];

        return $templateInfo;
    }

    private function analyzeYAMLFile($filePath) {
        $content = file_get_contents($filePath);
        $yamlInfo = [
            'path' => $filePath,
            'size' => strlen($content),
            'lines' => substr_count($content, "\n") + 1,
        ];

        if (function_exists('yaml_parse')) {
            $yamlContent = yaml_parse($content);
            $yamlInfo['structure'] = $this->summarizeYAMLStructure($yamlContent);
        } else {
            $yamlInfo['note'] = 'YAML extension not available. Install php-yaml for detailed analysis.';
        }

        return $yamlInfo;
    }

    private function summarizeYAMLStructure($data, $depth = 0, $maxDepth = 3) {
        if ($depth >= $maxDepth) {
            return '...';
        }

        $summary = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $summary[$key] = $this->summarizeYAMLStructure($value, $depth + 1, $maxDepth);
            } else {
                $summary[$key] = gettype($value);
            }
        }
        return $summary;
    }

    private function analyzeSQLFile($filePath) {
        $content = file_get_contents($filePath);
        $sqlInfo = [
            'path' => $filePath,
            'size' => strlen($content),
            'lines' => substr_count($content, "\n") + 1,
            'tables' => [],
        ];

        // Extract table names
        preg_match_all('/CREATE TABLE `?([a-zA-Z0-9_]+)`?/i', $content, $matches);
        $sqlInfo['tables'] = $matches[1];
        return $sqlInfo;
    }

    private function analyzeXMLFile($filePath) {
        $content = file_get_contents($filePath);
        $xmlInfo = [
            'path' => $filePath,
            'size' => strlen($content),
            'lines' => substr_count($content, "\n") + 1,
            'root_element' => '',
            'namespaces' => [],
        ];

        $xml = simplexml_load_string($content);
        if ($xml !== false) {
            $xmlInfo['root_element'] = $xml->getName();
            $namespaces = $xml->getNamespaces(true);
            foreach ($namespaces as $prefix => $namespace) {
                $xmlInfo['namespaces'][] = [
                    'prefix' => $prefix,
                    'uri' => $namespace,
                ];
            }
        } else {
            $xmlInfo['error'] = 'Failed to parse XML';
        }

        return $xmlInfo;
    }

    private function checkCriticalFiles() {
        $criticalFiles = [
            'ext_emconf.php',
            'ext_localconf.php',
            'ext_tables.php',
            'Configuration/TCA/Overrides/pages.php',
            'Configuration/TypoScript/setup.typoscript',
            'Configuration/TypoScript/constants.typoscript',
            'composer.json',
            'ext_icon.png',
            'Documentation/Index.rst',
            'Resources/Private/Language/locallang.xlf',
        ];

        $this->report['critical_files'] = [];

        foreach ($criticalFiles as $file) {
            $filePath = $this->extensionPath . DIRECTORY_SEPARATOR . $file;
            if (file_exists($filePath)) {
                $this->report['critical_files'][$file] = [
                    'status' => 'Present',
                    'size' => filesize($filePath),
                    'last_modified' => date("Y-m-d H:i:s", filemtime($filePath)),
                ];
            } else {
                $this->report['critical_files'][$file] = ['status' => 'Missing'];
            }
        }
    }

    private function analyzeExtensionStructure() {
        $structure = $this->scanDirectory($this->extensionPath);
        $this->report['extension_structure'] = $structure;
    }

    private function scanDirectory($dir) {
        $result = [];
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '.git') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $result[$file] = $this->scanDirectory($path);
            } else {
                $result[] = [
                    'name' => $file,
                    'size' => filesize($path),
                    'last_modified' => date("Y-m-d H:i:s", filemtime($path)),
                ];
            }
        }
        return $result;
    }

    private function analyzeConfigurationFiles() {
        $configFiles = [
            'Configuration/TCA' => 'TCA',
            'Configuration/FlexForms' => 'FlexForms',
            'Configuration/Services.yaml' => 'Services',
            'Configuration/TypoScript' => 'TypoScript',
        ];

        foreach ($configFiles as $path => $type) {
            $fullPath = $this->extensionPath . DIRECTORY_SEPARATOR . $path;
            if (is_dir($fullPath)) {
                $this->report['configuration'][$type] = $this->analyzeConfigDir($fullPath);
            } elseif (file_exists($fullPath)) {
                $this->report['configuration'][$type] = $this->analyzeConfigFile($fullPath);
            }
        }
    }

    private function analyzeConfigDir($dir) {
        $result = [];
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $result[$file] = $this->analyzeConfigDir($path);
            } else {
                $result[$file] = $this->analyzeConfigFile($path);
            }
        }
        return $result;
    }

    private function analyzeConfigFile($filePath) {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        switch ($extension) {
            case 'php':
                return $this->analyzePHPConfigFile($filePath);
            case 'yaml':
            case 'yml':
                return $this->analyzeYAMLFile($filePath);
            case 'typoscript':
            case 'ts':
                return $this->analyzeTypoScriptFile($filePath);
            case 'xml':
                return $this->analyzeXMLFile($filePath);
            default:
                return [
                    'path' => $filePath,
                    'size' => filesize($filePath),
                    'last_modified' => date("Y-m-d H:i:s", filemtime($filePath)),
                ];
        }
    }

    private function analyzePHPConfigFile($filePath) {
        $content = file_get_contents($filePath);
        $tokens = token_get_all($content);
        $configInfo = [
            'path' => $filePath,
            'size' => strlen($content),
            'lines' => substr_count($content, "\n") + 1,
            'variables' => [],
            'functions' => [],
        ];

        foreach ($tokens as $index => $token) {
            if (is_array($token)) {
                if ($token[0] === T_VARIABLE) {
                    $configInfo['variables'][] = $token[1];
                } elseif ($token[0] === T_FUNCTION) {
                    $configInfo['functions'][] = $this->getFunctionName($tokens, $index);
                }
            }
        }

        return $configInfo;
    }

    private function analyzeDependencies() {
        $composerJson = $this->extensionPath . DIRECTORY_SEPARATOR . 'composer.json';
        if (file_exists($composerJson)) {
            $content = file_get_contents($composerJson);
            $composerData = json_decode($content, true);
            
            if (isset($composerData['require'])) {
                $this->report['dependencies']['require'] = $composerData['require'];
            }
            
            if (isset($composerData['require-dev'])) {
                $this->report['dependencies']['require-dev'] = $composerData['require-dev'];
            }
        }

        $extEmconf = $this->extensionPath . DIRECTORY_SEPARATOR . 'ext_emconf.php';
        if (file_exists($extEmconf)) {
            $_EXTKEY = basename($this->extensionPath);
            include $extEmconf;
            if (isset($EM_CONF) && isset($EM_CONF[$_EXTKEY]['constraints'])) {
                $this->report['dependencies']['typo3'] = $EM_CONF[$_EXTKEY]['constraints'];
            }
        }
    }

    private function analyzeDatabase() {
        $sqlFiles = glob($this->extensionPath . '/ext_tables.sql');
        $sqlFiles = array_merge($sqlFiles, glob($this->extensionPath . '/sql/*.sql'));

        foreach ($sqlFiles as $sqlFile) {
            $this->report['database'][] = $this->analyzeSQLFile($sqlFile);
        }
    }

    private function saveReport() {
        $reportPath = $this->extensionPath . DIRECTORY_SEPARATOR . 'extension_analysis_report.json';
        file_put_contents($reportPath, json_encode($this->report, JSON_PRETTY_PRINT));
        echo "Rapport d'analyse généré à : $reportPath\n";
    }
}

// Exécution du script
echo "Entrez le chemin du répertoire de l'extension TYPO3 : ";
$handle = fopen("php://stdin", "r");
$extensionPath = trim(fgets($handle));

$analyzer = new TYPO3ExtensionAnalyzer($extensionPath);
$analyzer->analyzeExtension();