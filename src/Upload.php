<?php

namespace Pair;

/**
 * This class manages http file uploads.
 */
class Upload {

	/**
	 * File name, without path, as coming by $_FILE variable.
	 * @var string
	 */
	protected $filename;

	/**
	 * File size (in bytes), as coming by $_FILE variable.
	 * @var int
	 */
	protected $filesize;

	/**
	 * Absolute path where store uploaded file, with trailing slash.
	 * @var string
	 */
	protected $path;

	/**
	 * Array key error as coming by $_FILE variable.
	 * @var string
	 */
	protected $fileError;

	/**
	 * Array key tmp_name as coming by $_FILE variable.
	 * @var string
	 */
	protected $fileTmpname;

	/**
	 * Array key type as coming by $_FILE variable.
	 * @var string
	 */
	protected $fileType;

	/**
	 * MIME data for this file.
	 * @var string
	 */
	protected $mime;

	/**
	 * File type (audio,document,flash,image,movie,unknown)
	 * @var string
	 */
	protected $type;

	/**
	 * File extension, if exists.
	 * @var string
	 */
	protected $ext;

	/**
	 * MD5 file hash.
	 * @var string
	 */
	protected $hash;

	/**
	 * The former file name.
	 * @var string
	 */
	protected $formerName;

	/**
	 * List of all errors tracked.
	 * @var array
	 */
	private $errors = array();

	/**
	 * Constructor, sets file uploaded variables as object properties.
	 *
	 * @param	string	HTTP field name.
	 */
	public function __construct(string $fieldName) {

		// check on field name
		if (isset($_FILES[$fieldName])) {

			// assign file content array
			$file = $_FILES[$fieldName];
			
			try {
				// assign array values to the object properties
				$this->filename		= $file['name'];
				$this->formerName	= $file['name'];
				$this->filesize		= $file['size'];
				$this->fileError	= $file['error'];
				$this->fileTmpname	= $file['tmp_name'];
				$this->fileType		= $file['type'];
				$this->ext			= strtolower(substr($this->filename,strrpos($this->filename,'.')+1));

				// Sets MIME and type
				$info = $this->getMime($file['tmp_name']);
				$this->mime = $info->mime; // deprecated
				$this->type = $info->type; // deprecated

				// sets file hash
				$this->hash	= md5_file($file['tmp_name']);

				// sets the upload error as readable message
				if (UPLOAD_ERR_OK != $this->fileError) {
					$this->setErrorMessage();
				}	

			} catch (\Exception $e) {

				$this->setError('Unexpected $_FILES[\'' . $fieldName . '\' struct raised an error: ' . $e->getMessage());

			}

		} else {

			$this->setError('Field name “' . $fieldName . '” not found in $_FILES array.');

		}

	}

	public function __get(string $name) {

		return $this->$name;

	}

	/**
	 * Manages saving of an upload file with POST.
	 *
	 * @param	string	Absolute destination folder for the file to be saved, with or without trailing slash.
	 * @param	string	Optional new file name, if NULL will be the same as uploaded.
	 * @param	bool	Optional flag to save with random file name, default FALSE.
	 *
	 * @return	bool	TRUE if no errors.
	 */
	public function save(string $path, string $name=NULL, bool $random=FALSE): bool {

		// check upload errors
		if (UPLOAD_ERR_OK != $this->fileError) {
			$this->setErrorMessage();
			return FALSE;
		}

		// fixes path if not containing trailing slash
		Utilities::fixTrailingSlash($path);
		$this->path = APPLICATION_PATH . '/' . $path;  

		// sanitize file-name
		$this->filename = Utilities::localCleanFilename($this->filename);

		if ($random) {
			$this->filename = Utilities::randomFilename($this->filename,$this->path);
		} else if ($name) {
			$this->filename = Utilities::uniqueFilename($name,$this->path);
		} else {
			$this->filename = Utilities::uniqueFilename($this->filename,$this->path);
		}

		// checks that file doesn’t exists
		if (file_exists($this->path . $this->filename)) {
			$this->setError('A file with same name has been found at the path ' . $this->path . $this->filename);
			return FALSE;
		}

		// checks that destination folder exists and is writable
		if (!is_dir($this->path) or !is_readable($this->path)) {

			// if not, will creates
			$old = umask(0);
			if (!mkdir($this->path, 0777, TRUE)) {
				$this->setError('Folder ' . $this->path . ' creation doesn’t succeded');
				return FALSE;
			}
			umask($old);

		// checks that new folder is writable
		} else if (!is_writable($this->path)) {
			$this->setError('New folder ' . $this->path . ' is not writable');
			return FALSE;
		}

		// checks file moving
		if (move_uploaded_file($this->fileTmpname, $this->path . $this->filename)) {

			// sets file permissions
			if (!chmod($this->path . $this->filename, 0777)) {
				$this->setError('Permissions set ' . $this->path . $this->filename . ' doesn’t succeded');
			}

		} else {
			$this->setError('Error moving temporary file into the path ' . $this->path . $this->filename);
			return FALSE;
		}

		return TRUE;

	}

	/**
	 * Manages saving the uploaded file directly to an Amazon S3 folder with the specified file name.
	 * @param	string	Relative destination path on Amazon S3.
	 * @return	bool	TRUE if no errors.
	 */
	public function saveS3(string $filePath): bool {

		// check upload errors
		if (UPLOAD_ERR_OK != $this->fileError) {
			$this->setErrorMessage();
			return FALSE;
		}

		// sanitize file-name
		$this->filename = Utilities::localCleanFilename($this->filename);

		$amazonS3 = new AmazonS3();
		return $amazonS3->put($this->fileTmpname, $filePath);

	}

	/**
	 * Sets an error message based on the value of the fileError element in the array.
	 * @return void 
	 */
	private function setErrorMessage(): void {

		if (UPLOAD_ERR_OK == $this->fileError) {
			return;
		}

		// switches on proper error result
		switch ($this->fileError) {

			case UPLOAD_ERR_INI_SIZE:
				$this->setError('Uploaded file exceeds upload_max_filesize parameter set in php.ini: (' . ini_get('upload_max_filesize') . ')');
				break;

			case UPLOAD_ERR_FORM_SIZE:
				$this->setError('Uploaded file (' . $this->filesize  . ') exceeds MAX_FILE_SIZE attribute set in HTML form-field.');
				break;

			case UPLOAD_ERR_PARTIAL:
				$this->setError('File was uploaded partially');
				break;

			case UPLOAD_ERR_NO_FILE:
				$this->setError('No file set for upload');
				break;

			case UPLOAD_ERR_NO_TMP_DIR:
				$this->setError('Temporary file directory is missing');
				break;

			case UPLOAD_ERR_CANT_WRITE:
				$this->setError('Writing of file is failed');
				break;

			case UPLOAD_ERR_EXTENSION:
				$this->setError('File upload failed because of unvalid file extension');
				break;

			default:
				$this->setError('Unexpected file upload error');
				break;

		}

	}

	/**
	 * Will returns Mime and Type for the file as parameter.
	 * @param	string	Path to file.
	 * @return	\stdClass
	 */
	private function getMime(string $file): \stdClass {

		$info	= new \stdClass;

		$audio	= array ('audio/basic','audio/mpeg');//,'audio/x-aiff','audio/x-pn-realaudio','audio/wav','audio/x-wav');
		$docs	= array ('text/plain','application/pdf','application/msword','application/vnd.ms-excel','application/vnd.ms-powerpoint');
		$flash	= array ('application/x-shockwave-flash');
		$images = array ('image/gif','image/jpeg','image/png','image/svg+xml','image/tiff');
		$movies = array ('video/mpeg','video/mp4','video/quicktime','video/webm','video/x-flv','video/x-msvideo','video/x-ms-asf');
		$zip	= array ('application/zip');

		// reads MIME with the best function
		if (function_exists('mime_content_type')) {

			$info->mime = mime_content_type($file);

		} else if (extension_loaded('fileinfo')) {

			$const = defined('FILEINFO_MIME_TYPE') ? FILEINFO_MIME_TYPE : FILEINFO_MIME;
			$finfo = finfo_open($const);
			$info->mime = finfo_file($finfo, $file);
			finfo_close($finfo);

		} else {

			$this->setError('No extensions available for this file');
			$info->mime = NULL;
			$info->type = NULL;
			return $info;

		}

		// parses variable’s MIME and set its type
		if (in_array($info->mime, $docs)) {
			$info->type = 'document';
		} else if (in_array($info->mime, $movies)) {
			$info->type = 'movie';
		} else if (in_array($info->mime, $images)) {
			$info->type = 'image';
		} else if (in_array($info->mime, $flash)) {
			$info->type = 'flash';
		} else if (in_array($info->mime, $audio)) {
			$info->type = 'audio';
		} else if (in_array($info->mime, $zip)) {
			$info->type = 'zip';
		} else {
			$info->type = 'unknown';
		}

		return $info;

	}

	/**
	 * Will returns percentual of upload progress with APC.
	 * @param	string	UniqueID del caricamento
	 * @return	float
	 */
	public static function getUploadProgress(string $uniqueId): float {

		if (function_exists('apc_fetch')) {
			$upload = apc_fetch('upload_' . $uniqueId);
			if ($upload['done']) {
				$percent = 100.0;
			} else if (0 == $upload['total']) {
				$percent = 0.0;
			} else {
				$percent = round(($upload['current'] / $upload['total'] * 100), 0);
			}
			return $percent;
		} else {
			return 0.0;
		}

	}

	/**
	 * Will sets an error on queue of main Application singleton object.
	 * @param	string	Error text.
	 * @return	void
	 */
	private function setError(string $error): void {

		$this->errors[] = $error;
		Logger::error($error);

	}

	/**
	 * Returns text of latest error. In case of no errors, returns NULL.
	 * @return NULL|string
	 */
	public function getLastError(): ?string {

		return (count($this->errors) ? end($this->errors) : NULL);

	}

}
