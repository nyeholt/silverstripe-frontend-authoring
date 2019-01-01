<?php

namespace Symbiote\FrontendEditing;

use SilverStripe\Assets\Upload_Validator;
use SilverStripe\Assets\File;


/**
 *
 *
 * @author marcus
 */
class ContentUploadValidator extends Upload_Validator
{
    public function validate() {
		// we don't validate for empty upload fields yet
		if(!isset($this->tmpFile['name']) || empty($this->tmpFile['name'])) return true;
		$isRunningTests = false;
		if(!isset($this->tmpFile['tmp_name']) && !$isRunningTests) {
			$this->errors[] = _t('File.NOVALIDUPLOAD', 'File is not a valid upload');
			return false;
		}
		$pathInfo = pathinfo($this->tmpFile['name']);
		// filesize validation
		if(!$this->isValidSize()) {
			$ext = (isset($pathInfo['extension'])) ? $pathInfo['extension'] : '';
			$arg = File::format_size($this->getAllowedMaxFileSize($ext));
			$this->errors[] = _t(
				'File.TOOLARGE',
				'File size is too large, maximum {size} allowed',
				'Argument 1: File size (e.g. 1MB)',
				array('size' => $arg)
			);
			return false;
		}
		// extension validation
		if(!$this->isValidExtension()) {
			$this->errors[] = _t(
				'File.INVALIDEXTENSION',
				'Extension is not allowed (valid: {extensions})',
				'Argument 1: Comma-separated list of valid extensions',
				array('extensions' => wordwrap(implode(', ', $this->allowedExtensions)))
			);
			return false;
		}
		return true;
	}
}
