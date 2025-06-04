<?php
class CodeExecutor {
    private $supported_languages = ['javascript', 'python', 'java', 'cpp', 'sql'];
    private $temp_dir;
    private $timeout = 10; // seconds
    
    public function __construct() {
        $this->temp_dir = sys_get_temp_dir() . '/skillverge_code/';
        if (!is_dir($this->temp_dir)) {
            mkdir($this->temp_dir, 0755, true);
        }
    }
    
    public function executeCode($code, $language, $input = '') {
        if (!in_array($language, $this->supported_languages)) {
            return [
                'success' => false,
                'error' => 'Unsupported language: ' . $language,
                'output' => ''
            ];
        }
        
        $filename = uniqid() . '_' . time();
        
        try {
            switch ($language) {
                case 'javascript':
                    return $this->executeJavaScript($code, $filename, $input);
                case 'python':
                    return $this->executePython($code, $filename, $input);
                case 'java':
                    return $this->executeJava($code, $filename, $input);
                case 'cpp':
                    return $this->executeCpp($code, $filename, $input);
                case 'sql':
                    return $this->executeSQL($code, $filename);
                default:
                    return [
                        'success' => false,
                        'error' => 'Language not implemented',
                        'output' => ''
                    ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'output' => ''
            ];
        }
    }
    
    private function executeJavaScript($code, $filename, $input) {
        $filepath = $this->temp_dir . $filename . '.js';
        file_put_contents($filepath, $code);
        
        $command = "timeout {$this->timeout} node {$filepath}";
        if ($input) {
            $command = "echo " . escapeshellarg($input) . " | " . $command;
        }
        
        $output = shell_exec($command . ' 2>&1');
        unlink($filepath);
        
        return [
            'success' => true,
            'output' => $output ?: 'No output',
            'error' => ''
        ];
    }
    
    private function executePython($code, $filename, $input) {
        $filepath = $this->temp_dir . $filename . '.py';
        file_put_contents($filepath, $code);
        
        $command = "timeout {$this->timeout} python3 {$filepath}";
        if ($input) {
            $command = "echo " . escapeshellarg($input) . " | " . $command;
        }
        
        $output = shell_exec($command . ' 2>&1');
        unlink($filepath);
        
        return [
            'success' => true,
            'output' => $output ?: 'No output',
            'error' => ''
        ];
    }
    
    private function executeJava($code, $filename, $input) {
        $filepath = $this->temp_dir . $filename . '.java';
        $classname = 'Solution';
        
        // Extract class name from code
        if (preg_match('/public\s+class\s+(\w+)/', $code, $matches)) {
            $classname = $matches[1];
        }
        
        file_put_contents($filepath, $code);
        
        // Compile
        $compile_command = "javac {$filepath} 2>&1";
        $compile_output = shell_exec($compile_command);
        
        if (strpos($compile_output, 'error') !== false) {
            unlink($filepath);
            return [
                'success' => false,
                'error' => 'Compilation error: ' . $compile_output,
                'output' => ''
            ];
        }
        
        // Execute
        $class_file = $this->temp_dir . $classname . '.class';
        $command = "timeout {$this->timeout} java -cp {$this->temp_dir} {$classname}";
        if ($input) {
            $command = "echo " . escapeshellarg($input) . " | " . $command;
        }
        
        $output = shell_exec($command . ' 2>&1');
        
        // Cleanup
        unlink($filepath);
        if (file_exists($class_file)) {
            unlink($class_file);
        }
        
        return [
            'success' => true,
            'output' => $output ?: 'No output',
            'error' => ''
        ];
    }
    
    private function executeCpp($code, $filename, $input) {
        $source_file = $this->temp_dir . $filename . '.cpp';
        $executable = $this->temp_dir . $filename;
        
        file_put_contents($source_file, $code);
        
        // Compile
        $compile_command = "g++ -o {$executable} {$source_file} 2>&1";
        $compile_output = shell_exec($compile_command);
        
        if (!file_exists($executable)) {
            unlink($source_file);
            return [
                'success' => false,
                'error' => 'Compilation error: ' . $compile_output,
                'output' => ''
            ];
        }
        
        // Execute
        $command = "timeout {$this->timeout} {$executable}";
        if ($input) {
            $command = "echo " . escapeshellarg($input) . " | " . $command;
        }
        
        $output = shell_exec($command . ' 2>&1');
        
        // Cleanup
        unlink($source_file);
        if (file_exists($executable)) {
            unlink($executable);
        }
        
        return [
            'success' => true,
            'output' => $output ?: 'No output',
            'error' => ''
        ];
    }
    
    private function executeSQL($code, $filename) {
        // For demo purposes, simulate SQL execution
        // In production, you would connect to a sandboxed database
        
        $simulated_results = [
            'SELECT' => "id | name | email\n1 | John Doe | john@example.com\n2 | Jane Smith | jane@example.com",
            'INSERT' => "1 row(s) affected",
            'UPDATE' => "2 row(s) affected", 
            'DELETE' => "1 row(s) affected",
            'CREATE' => "Table created successfully",
            'DROP' => "Table dropped successfully"
        ];
        
        $code_upper = strtoupper(trim($code));
        foreach ($simulated_results as $keyword => $result) {
            if (strpos($code_upper, $keyword) === 0) {
                return [
                    'success' => true,
                    'output' => $result,
                    'error' => ''
                ];
            }
        }
        
        return [
            'success' => true,
            'output' => 'Query executed successfully',
            'error' => ''
        ];
    }
    
    public function validateCode($code, $language) {
        $issues = [];
        
        // Basic security checks
        $dangerous_functions = [
            'javascript' => ['eval', 'Function', 'require', 'import'],
            'python' => ['exec', 'eval', '__import__', 'open', 'file'],
            'java' => ['Runtime', 'ProcessBuilder', 'System.exit'],
            'cpp' => ['system', 'exec', 'popen', '#include <fstream>'],
            'sql' => ['DROP DATABASE', 'DROP SCHEMA', 'TRUNCATE']
        ];
        
        if (isset($dangerous_functions[$language])) {
            foreach ($dangerous_functions[$language] as $func) {
                if (stripos($code, $func) !== false) {
                    $issues[] = "Potentially dangerous function detected: {$func}";
                }
            }
        }
        
        // Check code length
        if (strlen($code) > 10000) {
            $issues[] = "Code is too long (max 10,000 characters)";
        }
        
        // Check for infinite loops (basic detection)
        if (preg_match('/while\s*$$\s*true\s*$$|for\s*$$\s*;\s*;\s*$$/', $code)) {
            $issues[] = "Potential infinite loop detected";
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues
        ];
    }
}
?>
