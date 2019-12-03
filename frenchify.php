<?php

$actions = [
    'sets the word'           => 'set',
    'echos the word'          => 'dire',
    'starts a class'          => 'classe',
    'starts a function'       => 'fonction',
    'displays debug message'  => 'deboguer',
    'displays debug message2' => 'var_dump',
    'exits script'            => 'au revoir',
];

$config = [
    'reserved' => [
        'words' => [
            $actions['sets the word'],
            $actions['echos the word'],
            $actions['starts a class'],
            $actions['starts a function'],
            $actions['displays debug message'],
            $actions['displays debug message2'],
            $actions['exits script'],
        ]
    ],
    'actions' => $actions
];

class scratchLexerAbstract
{
    private $stackTrace = [];
    private $builtWord = [];
    private $stack = false;
    private $storage = [
        'vars' => [],
        'objects' => [],
        'functions' => [],
        'activeobject'   => false,
        'activefunction' => false,
        'activeblock' => [],
    ];

    /**
     * Acts Like A Scratch Lexer
     *
     * @param  String $value
     * @return Object \scratchLexerAbstract
     */
    protected function scratchLexer($value)
    {
        $parser = $this->parser;
        $value  = $parser->removeComment($value);
        $value  = $parser->removeSpaces($value);

        $wordByword = preg_split("/\s/", $value);

        foreach ($wordByword as $index => $word) {

            // Record To Stack
            $trace = [$index, $word];

            // Release end brackets
            $this->checkEndBlocks($word);

            // Check if BuiltIn word
            if ($this->checkIfBuiltIn($word)) {
                if ($this->stack) {
                    $this->outPut($trace, $wordByword);
                    $this->stackTraceReset();
                }
                $this->stack = true;
            }

            if ($this->stack) {
                $this->addToStack($trace);
            }
        }

        return $this;
    }

    /**
     * Resets Stack Tracne
     * @return Self
     */
    private function stackTraceReset()
    {
        $this->stackTrace = [];
        return $this;
    }

    /**
     * Add Stack For Debugging
     *
     * @param array $trace [description]
     * @return Self
     */
    private function addToStack(array $trace)
    {
        $this->stackTrace[] = [$trace[0], $trace[1]];

        return $this;
    }

    /**
     * Check if there is an end Tag and parse it
     * @param  String $word
     * @return Self
     */
    private function checkEndBlocks($word)
    {
        if (preg_match('/{/', $word)) {
            $params  = $this->debugOut();
            $command = $params[0][1];
            $actions = $this->getActions();

            switch ($command) {
                case $actions['starts a class']:
                    $this->setClass($params);
                    break;
                case $actions['starts a function']:
                    $this->setFunction($params);
                    break;
            }

            // var_dump('START BLOCK '. $this->storage['lastblock'] . PHP_EOL);

        } elseif(preg_match('/}/', $word)) {

            if (empty($this->storage['activeblock'])) {
                echo("Undefined starting bracket on word number: ". $this->getLastLine() . PHP_EOL);
                $this->endAllStackDebug();
            }

            if (isset($this->storage['activeblock'][$this->storage['lastblock']])) {
                // var_dump('END BLOCK '. $this->storage['lastblock'] . PHP_EOL);
                unset($this->storage['activeblock'][$this->storage['lastblock']]);
            } else {
                foreach ($this->storage['activeblock'] as $key => $value) {
                    // var_dump('END BLOCK '. $key . PHP_EOL);
                    unset($this->storage['activeblock'][$key]);
                }
            }

            if ('function-'. $this->storage['activefunction'] === $this->storage['lastblock']) {
                $this->storage['activefunction'] = false;
            }

            if ('class-'. $this->storage['activeobject'] === $this->storage['lastblock']) {
                $this->storage['activeobject'] = false;
            }
        }

        return $this;
    }

    /**
     * Check if word is built in
     * @param  String $word [description]
     * @return Boolean [description]
     */
    private function checkIfBuiltIn($word)
    {
        foreach ($this->builtWord as $value) {
            if (preg_match('/'. preg_quote($value) .'/', $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse string into command
     * @param  Integer $index      Index
     * @param  String $word       Current Word
     * @param  array $wordByword word pool
     * @return Self
     */
    private function outPut($trace, $wordByword)
    {
        $stacks  = $this->debugOut();
        $actions = $this->getActions();

        $command = preg_match('/([^\(]*)\(*([^\)]*)/', $stacks[0][1], $match);
        $command = $match[1];

        unset($stacks[0]);

        $params = $stacks;

        switch ($command) {
            case $actions['sets the word']:
                $this->setVariable($params);
                break;
            case $actions['echos the word']:
                $this->echoOut($params);
                break;
            case $actions['starts a class']:
            case $actions['starts a function']:
                // Catch with endblock
                break;
            case $actions['displays debug message']:
                $this->displayParams(explode(',', $match[2]));
                break;
            case $actions['displays debug message2']:
                $this->displayParams(explode(',', $match[2]));
                break;
            default:
                echo "No Command: ". $command . PHP_EOL;
                break;
        }

        return $this;
    }

    /**
     * Description when some syntax error occurs
     * @return void
     */
    private function endAllStackDebug()
    {
        echo("Stack Trace: ". PHP_EOL);
        var_dump($this->storage);
        die();
    }

    /**
     * Adding Functions
     * @param array $params function Name
     */
    private function setFunction($params)
    {
        $name = $params[1][1];

        if ($this->storage['activefunction'] !== false) {
            echo("Cannot set function inside function ". $name . " on word number: ". $this->getLastLine() . PHP_EOL);
            $this->endAllStackDebug();
        }

        if ($this->storage['activeobject'] !== false) {
            $this->storage['objects'][$this->storage['activeobject']][$name] = [];
        } else {
            $this->storage['functions'][$name] = [];
        }

        $this->storage['activefunction'] = $name;
        $this->storage['activeblock']['function-'. $name] = true;
        $this->storage['lastblock'] = 'function-'. $name;

        return $this;
    }

    /**
     * Adding Class Objects
     * @param array $params class Name
     */
    private function setClass($params)
    {
        $name = $params[1][1];

        if ($this->storage['activefunction'] !== false) {
            echo("Cannot set class inside function ". $name . " on word number: ". $this->getLastLine() . PHP_EOL);
            $this->endAllStackDebug();
        }

        if ($this->storage['activeobject'] !== false) {
            echo("Cannot set class inside class ". $name . " on word number: ". $this->getLastLine() . PHP_EOL);
            $this->endAllStackDebug();
        }

        $this->storage['objects'][$name] = [];
        $this->storage['activeobject'] = $name;
        $this->storage['activeblock']['class-'. $name] = true;
        $this->storage['lastblock'] = 'class-'. $name;

        return $this;
    }

    /**
     * Echo parameters
     * @param  array $params echo parameters
     * @return Self
     */
    private function echoOut($params)
    {
        echo $this->convertArrayParamsToString($params) . PHP_EOL;

        return $this;
    }

    /**
     * Display parameters
     * @param  array $Params
     * @return Self
     */
    private function displayParams($params)
    {
        $valueArray = [];

        foreach ($params as $key => $param) {
            $valueArray[] = $this->convertVariableToString($param);
        }

        echo implode(',', $valueArray) . PHP_EOL;

        return $this;
    }

    /**
     * Set Variable To Storage
     * @param array $params Params to be set as variables
     */
    private function setVariable($params)
    {
        $name = $params[1][1];

        unset($params[1]);
        unset($params[2]);

        $value = $this->convertArrayParamsToString($params);

        if (preg_match('/^ce->/', $name)) {
            $this->setClassVariable($name, $value);
        } else {
            $this->storage['vars'][$name] = $value;
        }

        return $this;
    }

    private function setClassVariable($name, $variable)
    {
        $name = str_replace('ce->', '', $name);

        if (!isset($this->storage['objects'][$this->storage['activeobject']])) {
            echo('Invalid use of "this" outside classes');
            $this->endAllStackDebug();
        }

       $this->storage['objects'][$this->storage['activeobject']][$name] = $variable;
    }

    /**
     * Convert Array to String
     * @param  array $params
     * @return String
     */
    private function convertArrayParamsToString($params)
    {
        $valueArray = [];

        foreach ($params as $key => $param) {
            $valueArray[] = $this->convertVariableToString($param[1]);
        }

        return implode(' ', $valueArray);
    }

    /**
     * Convert Variable To String
     * @param  String $param string to check if variable
     * @return String
     */
    private function convertVariableToString($param)
    {
        if (preg_match('/\$(\w+[->]*\w*)/', $param, $match)) {
            // for classes
            $classNameVar = preg_replace('/^ce->/', '', $match[1]);

            if (isset($this->storage['vars'][$match[1]])) {
                return $this->storage['vars'][$match[1]];
            } elseif (isset($this->storage['objects'][$this->storage['activeobject']][$classNameVar])) {
                return $this->storage['objects'][$this->storage['activeobject']][$classNameVar];
            } else {
                echo("Undefined Variable ". $match[0] . " on word number: ". $this->getLastLine() . PHP_EOL);
                $this->endAllStackDebug();
            }
        }

        return $param;
    }

    /**
     * get Last Line of Stack Trace
     * @return Int Line
     */
    private function getLastLine()
    {
        foreach ($this->debugOut() as $key => $line) {
            $lastLine = $line;
        }

        return $lastLine[0];
    }

    /**
     * Add Words as an alias function
     *
     * @param string $string alias to be added
     * @return Object \scratchLexerAbstract
     */
    protected function addWords($string)
    {
        $this->builtWord[] = $string;

        return $this;
    }

    /**
     * Retrieves Debug Output
     * @return array
     */
    protected function debugOut()
    {
        return $this->stackTrace;
    }
}

class Frenchify extends scratchLexerAbstract
{
    private $identifier;
    private $config;
    protected $parser;

    /**
     * Add Dependencies
     *
     * @param \Identifier $identifier
     * @param \Parser $identifier
     */
    public function __construct(
        \Identifier $identifier,
        \Parser $parser,
        array $config
    ) {
        $this->identifier = $identifier;
        $this->parser = $parser;
        $this->config = $config;
    }

    /**
     * Compiles file or string passed to frenchify command
     *
     * @param  array
     * @return String
     */
    public function compile(array $argv)
    {
        $identifier = $this->identifier;

        $stringToParse = $identifier->identifyFileOrString($argv);

        foreach ($this->getConfig()['words'] as $word) {
            $this->addWords($word);
        }

        $scratchLexer = $this->scratchLexer($stringToParse);

        return $scratchLexer->debugOut();
    }

    /**
     * Get Config Files
     *
     * @return array
     */
    private function getConfig()
    {
        if (!isset($this->config['reserved'])) {
            throw new \Exception('Config reserved not applicable');
        }

        return $this->config['reserved'];
    }

    /**
     * Get Config Files
     *
     * @return array
     */
    protected function getActions()
    {
        if (!isset($this->config['actions'])) {
            throw new \Exception('Config actions not applicable');
        }

        return $this->config['actions'];
    }
}

class Identifier
{
    /**
     * Check Wether is file or not
     *
     * @param  array $argv
     * @return boolean
     */
    private function isFile(array $argv)
    {
        return (isset($argv[1]) && preg_match('/\.french/', $argv[1]));
    }

    /**
     * Gets All Params not including base file
     *
     * @param  array
     * @return String
     */
    private function getAllParams(array $argv)
    {
        unset($argv[0]);

        return implode(" ", $argv);
    }

    /**
     * Identifies if string or not and returns string
     *
     * @param  array
     * @return String
     */
    public function identifyFileOrString(array $argv)
    {
        $return = $this->getAllParams($argv);

        if ($this->isFile($argv)) {
            $return = file_get_contents($argv[1]);
        }

        return $return;
    }
}

class Parser
{
    /**
     * Remove Comments from file
     *
     * @param  String $parse Parse to String
     * @return String
     */
    public function removeComment($parse)
    {
        $patterns[] = '/\/\*[^\/]*\*\//';
        $patterns[] = '/(\/\/.*)/';

        $replacements[] = '';
        $replacements[] = '';

        return preg_replace($patterns, $replacements, $parse);
    }

    /**
     * Remove extra spaces
     *
     * @param  String $parse Parse to String
     * @return String
     */
    public function removeSpaces($parse)
    {
        $patterns[] = '/^\s*/';
        $patterns[] = '/\s*$/';
        $patterns[] = '/[\s]{2,}/';

        $replacements[] = '';
        $replacements[] = '';
        $replacements[] = ' ';

        return preg_replace($patterns, $replacements, $parse);
    }
}

class FrenchifyFactory
{
    /**
     * @return Object \Frenchify
     */
    public function createService(array $config)
    {
        $identifier = new \Identifier;
        $parser     = new \Parser;

        return new \Frenchify(
            $identifier,
            $parser,
            $config
        );
    }
}

$frenchify = (new \FrenchifyFactory())->createService($config);
$frenchify->compile($argv);