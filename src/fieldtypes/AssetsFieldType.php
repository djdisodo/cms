<?php
namespace Craft;

/**
 * Assets fieldtype
 */
class AssetsFieldType extends BaseElementFieldType
{
	/**
	 * @access protected
	 * @var string $elementType The element type this field deals with.
	 */
	protected $elementType = 'Asset';

	/**
	 * @access protected
	 * @var string|null $inputJsClass The JS class that should be initialized for the input.
	 */
	protected $inputJsClass = 'Craft.AssetSelectInput';

	/**
	 * Template to use for field rendering
	 * @var string
	 */
	protected $inputTemplate = '_components/fieldtypes/Assets/input';

	/**
	 * Returns the label for the "Add" button.
	 *
	 * @access protected
	 * @return string
	 */
	protected function getAddButtonLabel()
	{
		return Craft::t('Add an asset');
	}

	/**
	 * Defines the settings.
	 *
	 * @access protected
	 * @return array
	 */
	protected function defineSettings()
	{
		$settings = parent::defineSettings();
		$settings['singleFolderPath'] = AttributeType::String;
		$settings['defaultUploadPath'] = AttributeType::String;
		$settings['useSingleFolder'] = AttributeType::Bool;

		return $settings;
	}

	/**
	 * Preps the settings before they're saved to the database.
	 *
	 * @param array $settings
	 * @return array
	 */
	public function prepSettings($settings)
	{
		if (!(isset($settings['singleFolderPath']) && $settings['singleFolderPath']))
		{
			$settings['singleFolderPath'] = '';
		}

		if (!(isset($settings['defaultUploadPath']) && $settings['defaultUploadPath']))
		{
			$settings['defaultUploadPath'] = '';
		}

		if (!(isset($settings['useSingleFolder']) && $settings['useSingleFolder']))
		{
			$settings['useSingleFolder'] = 0;
		}
		return $settings;
	}

	/**
	 * Returns the field's settings HTML.
	 *
	 * @return string|null
	 */
	public function getSettingsHtml()
	{
		$sources = array();

		foreach ($this->getElementType()->getSources() as $key => $source)
		{
			if (!isset($source['heading']))
			{
				$sources[] = array('label' => $source['label'], 'value' => $key);
			}
		}

		return craft()->templates->render('_components/fieldtypes/Assets/settings', array(
			'sources'  => $sources,
			'settings' => $this->getSettings(),
			'type'     => $this->getName()
		));
	}

	/**
	 * Returns the field's input HTML.
	 *
	 * @param string $name
	 * @param mixed  $criteria
	 * @return string
	 * @throws Exception
	 */
	public function getInputHtml($name, $criteria)
	{
		// Look for the single folder setting
		$settings = $this->getSettings();
		if (!empty($settings->singleFolderPath) && !empty($settings->useSingleFolder))
		{
			// It must start with a folder or a source.
			$folderPath = $settings->singleFolderPath;
			if (preg_match('/^\{((folder|source):[0-9]+)\}/', $folderPath, $matches))
			{
				// Is this a saved entry and can the path be resolved then?
				if ($this->element->id)
				{
					$folderPath = 'folder:'.$this->_resolveSourcePathToFolderId($folderPath);
				}
				else
				{
					// New entry, so we default to User's upload folder for this field
					$userModel = craft()->userSession->getUser();
					if (!$userModel)
					{
						throw new Exception(Craft::t("To use this Field, user must be logged in!"));
					}

					$userFolder = craft()->assets->getUserFolder($userModel);

					$folderName = 'field_' . $this->model->id;
					$elementFolder = craft()->assets->findFolder(array('parentId' => $userFolder->id, 'name' => $folderName));
					if (!($elementFolder))
					{
						$folderId = $this->_createSubFolder($userFolder, $folderName);
					}
					else
					{
						$folderId = $elementFolder->id;
					}
					IOHelper::ensureFolderExists(craft()->path->getAssetsTempSourcePath().$folderName);
					$folderPath = 'folder:'.$folderId;
				}
			}
		}
		else
		{
			$folderPath = null;
		}

		$variables = array();

		// If we have a source path, override the source variable
		if ($folderPath)
		{
			$variables['sources'] = $folderPath;
		}


		craft()->templates->includeJsResource('lib/fileupload/jquery.ui.widget.js');
		craft()->templates->includeJsResource('lib/fileupload/jquery.fileupload.js');
		return parent::getInputHtml($name, $criteria, $variables);
	}

	/**
	 * For all new entries, if the field is using a single folder setting, move the uploaded files.
	 */
	public function onAfterElementSave()
	{
		$handle = $this->model->handle;

		// See if we have uploaded file(s).
		if (!empty($_FILES['fields']['name'][$handle]))
		{
			// Normalize the uploaded files, so that we always have an array to parse.
			$uploadedFiles = array();

			if (!is_array($_FILES['fields']['name'][$handle]) && IOHelper::fileExists($_FILES['fields']['tmp_name'][$handle]) && $_FILES['fields']['size'][$handle])
			{
				$uploadedFiles[] = array(
					'name' => $_FILES['fields']['name'][$handle],
					'tmp_name' => $_FILES['fields']['tmp_name'][$handle]
				);
			}
			else
			{
				foreach ($_FILES['fields']['name'][$handle] as $index => $name)
				{
					if (IOHelper::fileExists($_FILES['fields']['tmp_name'][$handle][$index]) && $_FILES['fields']['size'][$handle][$index])
					{
						$uploadedFiles[] = array(
							'name' => $name,
							'tmp_name' => $_FILES['fields']['tmp_name'][$handle][$index]
						);
					}
				}
			}

			$fileIds = array();

			if (count($uploadedFiles))
			{
				$targetFolderId = $this->resolveSourcePath();

				if (!empty($targetFolderId))
				{
					foreach ($uploadedFiles as $file)
					{
						$tempPath = AssetsHelper::getTempFilePath($file['name']);
						move_uploaded_file($file['tmp_name'], $tempPath);
						$fileIds[] = craft()->assets->insertFileByLocalPath($tempPath, $file['name'], $targetFolderId);
					}
					$this->element->getContent()->{$handle} = $fileIds;

				}
			}
		}
		// No uploaded files, just good old-fashioned Assets field
		else
		{
			$filesToMove = $this->element->getContent()->{$handle};
			if (is_array($filesToMove) && count($filesToMove))
			{
				$targetFolderId = $this->_resolveSourcePathToFolderId($this->getSettings()->singleFolderPath);

				// Resolve all conflicts by keeping both
				$actions = array_fill(0, count($filesToMove), AssetsHelper::ActionKeepBoth);
				craft()->assets->moveFiles($filesToMove, $targetFolderId, '', $actions);
			}
		}

		parent::onAfterElementSave();
	}

	/**
	 * Resolve source path for uploading for this field.
	 *
	 * @return mixed|null
	 */
	public function resolveSourcePath()
	{
		$targetFolderId = null;
		$settings = $this->getSettings();
		if ($settings->useSingleFolder)
		{
			$targetFolderId = $this->_resolveSourcePathToFolderId($settings->singleFolderPath);
		}
		else
		{
			if ($this->getSettings()->defaultUploadPath)
			{
				$targetFolderId = $this->_resolveSourcePathToFolderId($settings->defaultUploadPath);
			}
			else
			{
				$sources = $settings->sources;
				if (!is_array($sources))
				{
					$sourceIds = craft()->assetSources->getViewableSourceIds();
					if ($sourceIds)
					{
						$sourceId = reset($sourceIds);
						$targetFolder = craft()->assets->findFolder(array('sourceId' => $sourceId, 'parentId' => FolderCriteriaModel::AssetsNoParent));
						if ($targetFolder)
						{
							$targetFolderId = $targetFolder->id;
						}
					}
				}
				else
				{
					$targetFolder = reset($sources);
					list ($bogus, $targetFolderId) = explode(":", $targetFolder);
				}
			}
		}

		return $targetFolderId;
	}

	/**
	 * Resolve a source path to it's folder ID by the source path and the matched source beginning.
	 *
	 * @param $sourcePath
	 * @return mixed
	 * @throws Exception
	 */
	private function _resolveSourcePathToFolderId($sourcePath)
	{
		preg_match('/^\{((folder|source):[0-9]+)\}/', $sourcePath, $matches);
		$parts = explode(":", $matches[1]);
		if ($parts[0] == 'folder')
		{
			$folder = craft()->assets->getFolderById($parts[1]);
		}
		else
		{
			$folder = craft()->assets->findFolder(array('sourceId' => $parts[1], 'parentId' => FolderCriteriaModel::AssetsNoParent));
		}

		// Do we have the folder?
		if (empty($folder))
		{
			throw new Exception (Craft::t("Cannot find the target folder."));
		}
		else
		{
			$sourceId = $folder->sourceId;

			// Prepare the path by parsing tokens and normalizing slashes.
			$sourcePath = trim(str_replace('{'.$matches[1].'}', '', $sourcePath), '/');
			$sourcePath = craft()->templates->renderObjectTemplate($sourcePath, $this->element);
			$pathParts = explode("/", $sourcePath);
			foreach ($pathParts as &$part)
			{
				$part = IOHelper::cleanFilename($part);
			}

			$sourcePath = join("/", $pathParts);

			if (strlen($sourcePath))
			{
				$sourcePath = $sourcePath.'/';
			}

			// Let's see if the folder already exists.
			$folderCriteria = array('sourceId' => $sourceId, 'path' => $folder->path . $sourcePath);
			$existingFolder = craft()->assets->findFolder($folderCriteria);

			// No dice, go over each folder in the path and create it if it's missing.
			if (!$existingFolder)
			{
				$parts = explode('/', $sourcePath);

				// Now make sure that every folder in the path exists.
				$currentFolder = $folder;
				foreach ($parts as $part)
				{
					if (empty($part))
					{
						continue;
					}
					$folderCriteria = array('parentId' => $currentFolder->id, 'name' => $part);
					$existingFolder = craft()->assets->findFolder($folderCriteria);
					if (!$existingFolder)
					{
						$folderId = $this->_createSubFolder($currentFolder, $part);
						$existingFolder = craft()->assets->getFolderById($folderId);
					}
					$currentFolder = $existingFolder;
				}
			}
			else
			{
				$currentFolder = $existingFolder;
			}
		}

		return $currentFolder->id;
	}

	/**
	 * Create a subfolder in a folder by it's name.
	 *
	 * @param $currentFolder
	 * @param $folderName
	 * @return mixed|null
	 */
	private function _createSubFolder($currentFolder, $folderName)
	{
		$response = craft()->assets->createFolder($currentFolder->id, $folderName);

		if ($response->isError() || $response->isConflict())
		{
			// If folder doesn't exist in DB, but we can't create it, it probably exists on the server.
			$newFolder = new AssetFolderModel(
				array(
					'parentId' => $currentFolder->id,
					'name' => $folderName,
					'sourceId' => $currentFolder->sourceId,
					'path' => trim($currentFolder->path . '/' . $folderName, '/') . '/'
				)
			);
			$folderId = craft()->assets->storeFolder($newFolder);
			return $folderId;
		}
		else
		{

			$folderId = $response->getDataItem('folderId');
			return $folderId;
		}
	}

}
