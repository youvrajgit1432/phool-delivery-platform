<?php
class MediaController {
    private $db;
    private $mediaModel;
    private $pathConfig;

    public function __construct($db) {
        $this->db = $db;
        $this->mediaModel = new Media($db);
        $this->pathConfig = PathConfig::getInstance();
    }

    // Security helper for input sanitization
    private function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    // Validate and get integer from input
    private function getValidatedInt($input, $default = 0) {
        $value = filter_var($input, FILTER_VALIDATE_INT);
        return $value !== false ? $value : $default;
    }

    // Home page media section (4 items)
    public function homeMedia() {
        try {
            $filters = ['limit' => 4];
            $stmt = $this->mediaModel->read($filters);
            
            if ($stmt) {
                $media_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $this->sanitizeOutput($media_items);
            }
            
            return [];
        } catch (Exception $e) {
            error_log("MediaController homeMedia error: " . $e->getMessage());
            return [];
        }
    }

    // Media gallery page
    public function index() {
        try {
            // Get and sanitize filters from request
            $category_id = isset($_GET['category']) ? $this->getValidatedInt($_GET['category']) : '';
            $media_type = isset($_GET['type']) ? $this->sanitizeInput($_GET['type']) : '';
            $search = isset($_GET['search']) ? $this->sanitizeInput($_GET['search']) : '';
            
            // Validate media_type
            if (!in_array($media_type, ['', 'image', 'video'])) {
                $media_type = '';
            }
            
            $filters = [];
            if (!empty($category_id)) $filters['category_id'] = $category_id;
            if (!empty($media_type)) $filters['media_type'] = $media_type;
            if (!empty($search)) $filters['search'] = $search;
            
            // Get media items
            $stmt = $this->mediaModel->read($filters);
            $media_items = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            
            // Process media items to include proper file paths
            $media_items = $this->processMediaPaths($media_items);
            
            // Get popular media
            $popular_stmt = $this->mediaModel->getPopularMedia(6);
            $popular_media = $popular_stmt ? $popular_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $popular_media = $this->processMediaPaths($popular_media);
            
            // Get categories for filter dropdown
            $categories_stmt = $this->mediaModel->getCategories();
            $categories = $categories_stmt ? $categories_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            
            return [
                'media_items' => $this->sanitizeOutput($media_items),
                'popular_media' => $this->sanitizeOutput($popular_media),
                'categories' => $this->sanitizeOutput($categories),
                'current_filters' => [
                    'category_id' => $category_id,
                    'media_type' => $media_type,
                    'search' => $search
                ]
            ];
        } catch (Exception $e) {
            error_log("MediaController index error: " . $e->getMessage());
            return [
                'media_items' => [],
                'popular_media' => [],
                'categories' => [],
                'current_filters' => []
            ];
        }
    }

    // Single media view
    public function view($id) {
        try {
            // Validate ID
            if (!$this->getValidatedInt($id)) {
                return false;
            }
            
            $this->mediaModel->id = $id;
            
            if ($this->mediaModel->readOne()) {
                $media_data = [
                    'id' => $this->mediaModel->id,
                    'title_en' => $this->mediaModel->title_en,
                    'title_ne' => $this->mediaModel->title_ne,
                    'description_en' => $this->mediaModel->description_en,
                    'description_ne' => $this->mediaModel->description_ne,
                    'file_path' => $this->mediaModel->file_path,
                    'thumbnail_path' => $this->mediaModel->thumbnail_path,
                    'custom_thumbnail' => $this->mediaModel->custom_thumbnail,
                    'media_type' => $this->mediaModel->media_type,
                    'file_size' => $this->mediaModel->file_size,
                    'duration' => $this->mediaModel->duration,
                    'category_id' => $this->mediaModel->category_id,
                    'category_name_en' => $this->mediaModel->category_name_en,
                    'category_name_ne' => $this->mediaModel->category_name_ne,
                    'views_count' => $this->mediaModel->views_count,
                    'likes_count' => $this->mediaModel->likes_count,
                    'uploaded_by' => $this->mediaModel->uploaded_by,
                    'created_at' => $this->mediaModel->created_at,
                    'updated_at' => $this->mediaModel->updated_at
                ];
                
                // Process file paths
                $media_data = $this->processSingleMediaPaths($media_data);
                
                return $this->sanitizeOutput($media_data);
            } else {
                return false;
            }
        } catch (Exception $e) {
            error_log("MediaController view error for ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    // Process media paths for multiple items
    private function processMediaPaths($media_items) {
        if (!is_array($media_items)) {
            return $media_items;
        }
        
        foreach ($media_items as &$item) {
            $item = $this->processSingleMediaPaths($item);
        }
        
        return $media_items;
    }

    // Process file paths for single media item
    private function processSingleMediaPaths($media_item) {
        if (!is_array($media_item)) {
            return $media_item;
        }
        
        // Process main file path
        if (!empty($media_item['file_path'])) {
            $filename = basename($media_item['file_path']);
            $media_item['file_url'] = $this->pathConfig->getFilePath($filename, 'media');
        } else {
            $media_item['file_url'] = '';
        }
        
        // Process thumbnail path
        if (!empty($media_item['custom_thumbnail'])) {
            $thumb_filename = basename($media_item['custom_thumbnail']);
            $media_item['thumbnail_url'] = $this->pathConfig->getImagePath($thumb_filename, 'media_thumb');
        } elseif (!empty($media_item['thumbnail_path'])) {
            $thumb_filename = basename($media_item['thumbnail_path']);
            $media_item['thumbnail_url'] = $this->pathConfig->getImagePath($thumb_filename, 'media_thumb');
        } else {
            // Fallback thumbnail based on media type
            if ($media_item['media_type'] === 'video') {
                $media_item['thumbnail_url'] = $this->pathConfig->get('assets') . '/img/video-placeholder.jpg';
            } else {
                $media_item['thumbnail_url'] = $this->pathConfig->get('assets') . '/img/image-placeholder.jpg';
            }
        }
        
        return $media_item;
    }

    // Sanitize output data
    private function sanitizeOutput($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeOutput'], $data);
        }
        return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
    }

    // Helper method to get media upload directory path
    public function getMediaUploadDir() {
        return $this->pathConfig->filePath('media_uploads');
    }

    // Helper method to get thumbnail directory path
    public function getThumbnailDir() {
        return $this->pathConfig->filePath('media_uploads') . '/thumbs';
    }

    // Helper method to get full URL for media file
    public function getMediaUrl($filename) {
        return $this->pathConfig->getFilePath($filename, 'media');
    }

    // Helper method to get full URL for thumbnail
    public function getThumbnailUrl($filename) {
        return $this->pathConfig->getImagePath($filename, 'media_thumb');
    }
}
?>