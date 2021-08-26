<?php

declare(strict_types=1);

namespace Nordsec\SlimBootstrapBase\Services;

class ClassNameLoader
{
    const BUFFER_SIZE = 512;

    const BLOCK_START_SYMBOL = '{';

    /**
     * Retrieves FQDN from file
     *
     * @param string $file
     * @return string
     */
    public function loadFromFile($file)
    {
        $className = $this->loadFromDeclaredClasses($file);
        if (empty($className)) {
            $className = $this->loadFromContent($file);
        }

        return $className;
    }

    /**
     * Loads the class from the specified file and returns the name of the loaded class
     *
     * @param string $file
     * @return string
     */
    private function loadFromDeclaredClasses($file)
    {
        $classes = get_declared_classes();
        require_once $file;
        $diff = array_diff(get_declared_classes(), $classes);
        return reset($diff);
    }

    /**
     * Retrieves FQDN by tokenizing the contents of the file
     *
     * @param string $file
     * @return string
     */
    private function loadFromContent($file)
    {
        list($namespace, $class) = $this->getClassNameParts($file);
        if (empty($class)) {
            throw new \RuntimeException(sprintf('Could not find any classes in file "%s"', $file));
        }

        return sprintf('%s\\%s', $namespace, $class);
    }

    private function getClassNameParts($file)
    {
        $fp = fopen($file, 'r');
        $class = $namespace = $buffer = '';
        $i = 0;

        while (!$class) {
            if (feof($fp)) {
                break;
            }

            $buffer .= fread($fp, self::BUFFER_SIZE);
            if (!$this->bufferContainsClassDefinition($buffer)) {
                continue;
            }

            $tokens = token_get_all($buffer);

            for (; $i < count($tokens); $i++) {
                if ($tokens[$i][0] === T_NAMESPACE) {
                    for ($j = $i + 1; $j < count($tokens); $j++) {
                        if ($tokens[$j][0] === T_STRING) {
                            $namespace .= '\\' . $tokens[$j][1];
                        } elseif ($this->previousDefinitionEnded($tokens[$j])) {
                            break;
                        }
                    }
                }

                if ($tokens[$i][0] === T_CLASS) {
                    for ($j = $i + 1; $j < count($tokens); $j++) {
                        if ($tokens[$j] === self::BLOCK_START_SYMBOL) {
                            $class = $tokens[$i + 2][1];
                            return [$namespace, $class];
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param $buffer
     * @return bool
     */
    private function bufferContainsClassDefinition($buffer)
    {
        return strpos($buffer, self::BLOCK_START_SYMBOL) !== false;
    }

    /**
     * @param string $token
     * @return bool
     */
    private function previousDefinitionEnded($token)
    {
        return $token === self::BLOCK_START_SYMBOL || $token === ';';
    }
}
