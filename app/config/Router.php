<?php
// app/config/Router.php
class Router {
    private $routes = [];
    
    public function addRoute($pattern, $handler) {
        $this->routes[$pattern] = $handler;
    }
    
    public function matchRoute($url) {
        error_log("=== ROUTER DEBUG ===");
        error_log("URL to match: " . $url);
        
        // Handle empty URL (homepage)
        if ($url === '' || $url === '/') {
            if (isset($this->routes[''])) {
                error_log("✓ Homepage route matched");
                return $this->routes[''];
            }
        }
        
        // Remove leading/trailing slashes for consistency
        $url = trim($url, '/');
        error_log("URL after trim: " . $url);
        
        // Check for exact matches first
        if (isset($this->routes['/' . $url])) {
            error_log("✓ Exact match found: /" . $url);
            return $this->routes['/' . $url];
        }
        
        // Check exact match with leading slash
        if (isset($this->routes[$url])) {
            error_log("✓ Exact match found: " . $url);
            return $this->routes[$url];
        }
        
        // Check for pattern matches with parameters
        foreach ($this->routes as $pattern => $handler) {
            // Skip empty pattern (homepage)
            if ($pattern === '') continue;
            
            $clean_pattern = trim($pattern, '/');
            error_log("Testing pattern: " . $clean_pattern . " against URL: " . $url);
            
            // Convert route pattern to regex
            $regex = $this->patternToRegex($clean_pattern);
            
            if (preg_match($regex, $url, $matches)) {
                error_log("✓ Pattern match found!");
                
                // Extract named parameters
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }
                
                error_log("Params: " . print_r($params, true));
                return array_merge($handler, ['params' => $params]);
            }
        }
        
        error_log("✗ No route found for: " . $url);
        error_log("Available routes: " . implode(', ', array_keys($this->routes)));
        return false;
    }
    
    private function patternToRegex($pattern) {
        // Escape special regex characters
        $regex = preg_quote($pattern, '#');
        
        // Replace {param} with named capture groups
        $regex = preg_replace('/\\\{([a-zA-Z_]+)\\\}/', '(?P<$1>[^/]+)', $regex);
        
        return '#^' . $regex . '$#';
    }
    
    public function getRoutes() {
        return $this->routes;
    }
}
?>