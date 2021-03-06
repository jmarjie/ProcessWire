<?php

/**
 * ProcessWire Pagefiles
 *
 * Pagefiles are a collection of Pagefile objects.
 *
 * Typically a Pagefiles object will be associated with a specific field attached to a Page. 
 * There may be multiple instances of Pagefiles attached to a given Page (depending on what fields are in it's fieldgroup).
 * 
 * ProcessWire 2.x 
 * Copyright (C) 2010 by Ryan Cramer 
 * Licensed under GNU/GPL v2, see LICENSE.TXT
 * 
 * http://www.processwire.com
 * http://www.ryancramer.com
 *
 */

class Pagefiles extends WireArray {

	/**
	 * The Page object associated with these Pagefiles
	 *
	 */
	protected $page; 

	/**
	 * Items to be deleted when Page is saved
	 *
	 */
	protected $unlinkQueue = array();

	/**
	 * IDs of any hooks added in this instance, used by the destructor
	 *
	 */
	protected $hookIDs = array();

	/**
	 * Construct an instantance of Pagefiles 
	 *
	 * @param Page $page The page associated with this Pagefiles instance
	 *
	 */
	public function __construct(Page $page) {
		$this->setPage($page); 
	}

	public function __destruct() {
		$this->removeHooks();
	}

	protected function removeHooks() {
		if(count($this->hookIDs) && $this->page && $this->page->filesManager) {
			foreach($this->hookIDs as $id) $this->page->filesManager->removeHook($id); 
		}
	}

	public function setPage(Page $page) {
		$this->page = $page; 
	}

	/**
	 * Creates a new blank instance of itself. For internal use, part of the WireArray interface. 
	 *
	 * Adapted here so that $this->page can be passed to the constructor of a newly created Pagefiles. 
	 *
	 * @param array $items Array of items to populate (optional)
	 * @return WireArray
	 */
	public function makeNew() {
		$class = get_class($this); 
		$newArray = new $class($this->page); 
		return $newArray; 
	}

	/**
	 * When Pagefiles is cloned, ensure that the individual Pagefile items are also cloned
	 *
	 */
	public function __clone() {
		foreach($this as $key => $pagefile) {
			$this->set($key, clone $pagefile); 
		}
	}

	/**
	 * Per the WireArray interface, items must be of type Pagefile
	 *
	 */
	public function isValidItem($item) {
		return $item instanceof Pagefile;
	}

	/**
	 * Per the WireArray interface, items are indexed by Pagefile::basename
	 *
	 */
	public function getItemKey($item) {
		return $item->basename; 
	}

	/**
	 * Per the WireArray interface, return a blank Pagefile
	 *
	 */
	public function makeBlankItem() {
		return new Pagefile($this, ''); 
	}

	/**
	 * Get a value from this Pagefiles instance
	 *
	 * @param string $key
	 * @return mixed
	 *
	 */
	public function get($key) {
		if($key == 'page') return $this->page; 
		if($key == 'url') return $this->url();
		if($key == 'path') return $this->path(); 
		return parent::get($key); 
	}

	/**
	 * Add a new Pagefile item, or create one from it's filename and add it.
	 *
	 * @param Pagefile|string $item If item is a string (filename) then the Pagefile instance will be created automatically.
	 * @return this
	 *
	 */
	public function add($item) {

		if(is_string($item)) {
			$item = new Pagefile($this, $item); 
		}

		return parent::add($item); 
	}

	/**
	 * Make any removals take effect on disk
	 *
	 */
	public function hookPageSave() {
		foreach($this->unlinkQueue as $item) {
			$item->unlink();
		}
		$this->unlinkQueue = array();
		$this->removeHooks();
		return $this; 
	}

	/**
	 * Delete a pagefile item, hookable alias of remove()
	 *
	 * @param Pagefile $item
	 * @return this
	 *
	 */
	public function ___delete($item) {
		return $this->remove($item); 
	}

	/**
	 * Delete/remove a Pagefile item
	 *
	 * Deletes the filename associated with the Pagefile and removes it from this Pagefiles instance. 
	 *
	 * @param Pagefile $item
	 * @return this
	 *
	 */
	public function remove($item) {
		if(!$this->isValidItem($item)) throw new WireException("Invalid type to {$this->className}::remove(item)"); 
		// $item->unlink();
		if(!count($this->unlinkQueue)) {
			$this->hookIDs[] = $this->page->filesManager->addHookBefore('save', $this, 'hookPageSave'); 
		}
		$this->unlinkQueue[] = $item; 
		parent::remove($item); 
		return $this; 
	}

	/**
	 * Delete all files associated with this Pagefiles instance, leaving a blank Pagefiles instance. 
	 *
	 * @return this
	 *
	 */ 
	public function deleteAll() {
		foreach($this as $item) {
			$this->delete($item); 
		}

		return $this; 
	}

	/**
	 * Return the full disk path where files are stored
	 *
	 */
	public function path() {
		return $this->page->filesManager->path();
	}

	/**
	 * Returns the web accessible index URL where files are stored
	 *
	 */
	public function url() {
		return $this->page->filesManager->url();
	}

	/**
	 * Given a basename, this method returns a clean version containing valid characters 
	 *
	 * @param string $basename May also be a full path/filename, but it will still return a basename
	 * @param bool $originalize If true, it will generate an original filename if $basename already exists
	 * @return string
	 *
	 */ 
	public function cleanBasename($basename, $originalize = false) {

		$path = $this->path(); 
		$basename = strtolower(basename($basename)); 
		$basename = preg_replace('/[^-_.a-zA-Z0-9]/', '_', $basename); 
		if($originalize) { 
			$n = 0; 
			while(is_file($path . $basename)) {
				$basename = (++$n) . "_" . preg_replace('/^\d+_/', '', $basename); 
			}
		}
		return $basename; 
	}

	public function uncache() {
		$this->page = null;		
	}

}
