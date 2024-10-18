<?php

class UploadFiles {

    private $options = [
        'multiple' => true,
        'directory' => null,
        'filename' => 'random',
        'allowext' => true,
        'filesize' => 0, 
        'mime' => [],
        'strict' => false, // this means if its an array of files and 1 fails, should upload continue for others?
    ];
    private $resource;
    private $return = [
        'errors' => [],
        'values' => [],
    ];

    public function __construct(array $resource, array $options) {
        /*
            $options = [
                'multiple' => bool,
                'directory' => string | function - must return string,
                'filename' => random | default | function - must return string,
                'allowExt' => bool - defaults to true,
                'filesize' => int - defaults to 0 - means use the max value of php ini setting,
                'mime' => array - defaults to empty array - means dont check for mime type
                'strict' => bool - defaults to false - means if any of the files fail, continue to upload the successful ones
            ];
        */
        $this->resource = $resource;
        if (is_array($options)) {
            foreach($options as $key => $value) {
                $this->options[$key] = $value;
            }
        }
    }

    public function upload() {
        try {
            if ($this->isFileUploaded($this->resource)) { // this checks if the user uploaded an empty form
                if ($this->options['multiple'] && is_array($this->resource['name'])) return $this->uploadMultipleFiles($this->resource);
                if (! $this->options['multiple'] && is_array($this->resource['name'])) { //if we dont allow multiple files and the user finds a way to upload multiple files
                    return $this->uploadSingleFile([
                        'name' => $this->resource['name'][0],
                        'tmp_name' => $this->resource['tmp_name'][0],
                        'error' => $this->resource['error'][0],
                    ]);
                }
                return $this->uploadSingleFile($this->resource);
            }
        } catch (Exception $e) {
            var_dump($e);
        }
    }

    private function uploadSingleFile(array $resource) {
        // first check for errors
        if ($resource['error'] !== UPLOAD_ERR_OK) $this->return['error'] = [$resource['name'] => "upload error"];
        // next check for mime type
        if (! $this->isMimeTypeValid($resource['tmp_name'])) $this->return['error'] = [$resource['name'] => "invalid mime type"];
        // next check for file size
        if (! $this->isFileSizeValid($resource['tmp_name'])) $this->return['error'] = [$resource['name'] => "file too large"];

        if (count($this->return['errors']) > 0) return $return;

        $filename = $this->generateFileName($resource['name']);
        if (! move_uploaded_file( $resource['tmp_name'], $filename)) $this->return['errors'] = [$resource['name'] => "could not be moved to the upload directory"];
        else $this->return['values'][] = $filename;

        return $this->return;
    }

    private function uploadMultipleFiles(array $resource) {
        $total = count($resource['name']);

        // first check for errors
        for($i = 0; $i < $total; $i++) {
            // check for upload errors
            if ($resource['error'][$i] !== UPLOAD_ERR_OK) $this->return['errors'] = [$resource['name'][$i] => "upload error"];

            // next check for mime type
            if (! $this->isMimeTypeValid($resource['tmp_name'][$i])) $this->return['errors'] = [$resource['name'][$i] => 'invalid mime type'];

            // next check for file size
            if (! $this->isFileSizeValid($resource['tmp_name'][$i])) $this->return['errors'] = [$resource['name'][$i] => "file too large",];
        }

        if ($this->options['strict'] && count($this->return['errors']) > 0) return $return;

        // now move the files to the options-> directory
        for ($i = 0; $i < $total; $i++) {
            $filename = $this->generateFileName($resource['name'][$i]);
            if (! move_uploaded_file( $resource['tmp_name'][$i], $filename )) {
                $this->return['errors'] = [$resource['name'][$i] => "could not be moved to the upload directory"];
                break;
            }
            $this->return['values'][] = $filename;
        }
        return $this->return;
    }

    private function getMimeType($file): string {
        if (function_exists("mime_content_type")) {
            return mime_content_type($file);
        } else {
            $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file);
            finfo_close();
            return $mime;
        }
    }

    private function isMimeTypeValid($file): bool {
        return in_array( $this->getMimeType($file), $this->options['mime']);
    }

    private function isFileSizeValid($file): bool {
        return filesize($file) < $this->options['filesize'];
    }

    private function getFileExtension($file) {
        return pathinfo($file, PATHINFO_EXTENSION);
    }

    private function generateFileName($file) {
        $name;
        if (is_callable($this->options['filename'])) {
            $name = $this->options['filename']($file);
        } else {
            $name = uniqid();
            if ($this->options['allowext']) $name = $name . "." . $this->getFileExtension($file);
        }
        return $this->options['directory'] . $name;
    }

    private function isFileUploaded($resource) {
        if (is_array($resource['tmp_name'])) return (!empty($resource['tmp_name'][0] && is_uploaded_file($resource['tmp_name'][0])));
        return (!empty($resource['tmp_name'] && is_uploaded_file($resource['tmp_name'])));
    }
    
}

?>