/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Nikola Stojiljkovic <nikola.stojiljkovic(at)essentialdots.com>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

; (function($) {
	var
	/* hStorage version */
		HSTORAGE_VERSION = "0.4.4",

	/* detect a dollar object or create one if not found */
		$ = window.jQuery || window.$ || (window.$ = {}),

	/* check for a JSON handling support */
		JSON = {
			parse:
				window.JSON && (window.JSON.parse || window.JSON.decode) ||
				String.prototype.evalJSON && function(str){return String(str).evalJSON();} ||
				$.parseJSON ||
				$.evalJSON,
			stringify:
				Object.toJSON ||
				window.JSON && (window.JSON.stringify || window.JSON.encode) ||
				$.toJSON
		};

	// Break if no JSON support was found
	if(!('parse' in JSON) || !('stringify' in JSON)){
		throw new Error("No JSON support found, include //cdnjs.cloudflare.com/ajax/libs/json2/20110223/json2.js to page");
	}

	var
	/* This is the object, that holds the cached values */
		_storage = {__hstorage_meta:{CRC32:{}}},

	/* Actual browser storage (localStorage or globalStorage['domain']) */
		_storage_service = {hStorage:"{}"},

	/* DOM element for older IE versions, holds userData behavior */
		_storage_elm = null,

	/* How much space does the storage take */
		_storage_size = 0,

	/* which backend is currently used */
		_backend = false,

	/* onchange observers */
		_observers = {},

	/* timeout to wait after onchange event */
		_observer_timeout = false,

	/* last update time */
		_observer_update = 0,

	/* pubsub observers */
		_pubsub_observers = {},

	/* skip published items older than current timestamp */
		_pubsub_last = +new Date(),

	/* Next check for TTL */
		_ttl_timeout,

		/**
		 * XML encoding and decoding as XML nodes can't be JSON'ized
		 * XML nodes are encoded and decoded if the node is the value to be saved
		 * but not if it's as a property of another object
		 * Eg. -
		 *   $.hStorage.set("key", xmlNode);        // IS OK
		 *   $.hStorage.set("key", {xml: xmlNode}); // NOT OK
		 */
			_XMLService = {

			/**
			 * Validates a XML node to be XML
			 * based on jQuery.isXML function
			 */
			isXML: function(elm){
				var documentElement = (elm ? elm.ownerDocument || elm : 0).documentElement;
				return documentElement ? documentElement.nodeName !== "HTML" : false;
			},

			/**
			 * Encodes a XML node to string
			 * based on http://www.mercurytide.co.uk/news/article/issues-when-working-ajax/
			 */
			encode: function(xmlNode) {
				if(!this.isXML(xmlNode)){
					return false;
				}
				try{ // Mozilla, Webkit, Opera
					return new XMLSerializer().serializeToString(xmlNode);
				}catch(E1) {
					try {  // IE
						return xmlNode.xml;
					}catch(E2){}
				}
				return false;
			},

			/**
			 * Decodes a XML node from string
			 * loosely based on http://outwestmedia.com/jquery-plugins/xmldom/
			 */
			decode: function(xmlString){
				var dom_parser = ("DOMParser" in window && (new DOMParser()).parseFromString) ||
				                 (window.ActiveXObject && function(_xmlString) {
					                 var xml_doc = new ActiveXObject('Microsoft.XMLDOM');
					                 xml_doc.async = 'false';
					                 xml_doc.loadXML(_xmlString);
					                 return xml_doc;
				                 }),
					resultXML;
				if(!dom_parser){
					return false;
				}
				resultXML = dom_parser.call("DOMParser" in window && (new DOMParser()) || window, xmlString, 'text/xml');
				return this.isXML(resultXML)?resultXML:false;
			}
		};


	////////////////////////// PRIVATE METHODS ////////////////////////

	/**
	 * Initialization function. Detects if the browser supports DOM Storage
	 * or userData behavior and behaves accordingly.
	 */
	function _init(){
		/* Check if browser supports localStorage */
		var localStorageReallyWorks = false;
		if("localStorage" in window){
			try {
				window.localStorage.setItem('_tmptest', 'tmpval');
				localStorageReallyWorks = true;
				window.localStorage.removeItem('_tmptest');
			} catch(BogusQuotaExceededErrorOnIos5) {
				// Thanks be to iOS5 Private Browsing mode which throws
				// QUOTA_EXCEEDED_ERRROR DOM Exception 22.
			}
		}

		if(localStorageReallyWorks){
			try {
				if(window.localStorage) {
					_storage_service = window.localStorage;
					_backend = "localStorage";
					_observer_update = _storage_service.hStorage_update;
				}
			} catch(E3) {/* Firefox fails when touching localStorage and cookies are disabled */}
		}
		/* Check if browser supports globalStorage */
		else if("globalStorage" in window){
			try {
				if(window.globalStorage) {
					if(window.location.hostname == 'localhost'){
						_storage_service = window.globalStorage['localhost.localdomain'];
					}
					else{
						_storage_service = window.globalStorage[window.location.hostname];
					}
					_backend = "globalStorage";
					_observer_update = _storage_service.hStorage_update;
				}
			} catch(E4) {/* Firefox fails when touching localStorage and cookies are disabled */}
		}
		/* Check if browser supports userData behavior */
		else {
			_storage_elm = document.createElement('link');
			if(_storage_elm.addBehavior){

				/* Use a DOM element to act as userData storage */
				_storage_elm.style.behavior = 'url(#default#userData)';

				/* userData element needs to be inserted into the DOM! */
				document.getElementsByTagName('head')[0].appendChild(_storage_elm);

				try{
					_storage_elm.load("hStorage");
				}catch(E){
					// try to reset cache
					_storage_elm.setAttribute("hStorage", "{}");
					_storage_elm.save("hStorage");
					_storage_elm.load("hStorage");
				}

				var data = "{}";
				try{
					data = _storage_elm.getAttribute("hStorage");
				}catch(E5){}

				try{
					_observer_update = _storage_elm.getAttribute("hStorage_update");
				}catch(E6){}

				_storage_service.hStorage = data;
				_backend = "userDataBehavior";
			}else{
				_storage_elm = null;
				return;
			}
		}

		// Load data from storage
		_load_storage();

		// remove dead keys
		_handleTTL();

		// start listening for changes
		_setupObserver();

		// initialize publish-subscribe service
		_handlePubSub();

		// handle cached navigation
		if("addEventListener" in window){
			window.addEventListener("pageshow", function(event){
				if(event.persisted){
					_storageObserver();
				}
			}, false);
		}
	}

	/**
	 * Reload data from storage when needed
	 */
	function _reloadData(){
		var data = "{}";

		if(_backend == "userDataBehavior"){
			_storage_elm.load("hStorage");

			try{
				data = _storage_elm.getAttribute("hStorage");
			}catch(E5){}

			try{
				_observer_update = _storage_elm.getAttribute("hStorage_update");
			}catch(E6){}

			_storage_service.hStorage = data;
		}

		_load_storage();

		// remove dead keys
		_handleTTL();

		_handlePubSub();
	}

	/**
	 * Sets up a storage change observer
	 */
	function _setupObserver(){
		if(_backend == "localStorage" || _backend == "globalStorage"){
			if("addEventListener" in window){
				window.addEventListener("storage", _storageObserver, false);
			}else{
				document.attachEvent("onstorage", _storageObserver);
			}
		}else if(_backend == "userDataBehavior"){
			setInterval(_storageObserver, 1000);
		}
	}

	/**
	 * Fired on any kind of data change, needs to check if anything has
	 * really been changed
	 */
	function _storageObserver(){
		var updateTime;
		// cumulate change notifications with timeout
		clearTimeout(_observer_timeout);
		_observer_timeout = setTimeout(function(){

			if(_backend == "localStorage" || _backend == "globalStorage"){
				updateTime = _storage_service.hStorage_update;
			}else if(_backend == "userDataBehavior"){
				_storage_elm.load("hStorage");
				try{
					updateTime = _storage_elm.getAttribute("hStorage_update");
				}catch(E5){}
			}

			if(updateTime && updateTime != _observer_update){
				_observer_update = updateTime;
				_checkUpdatedKeys();
			}

		}, 25);
	}

	/**
	 * Reloads the data and checks if any keys are changed
	 */
	function _checkUpdatedKeys(){
		var oldCrc32List = JSON.parse(JSON.stringify(_storage.__hstorage_meta.CRC32)),
			newCrc32List;

		_reloadData();
		newCrc32List = JSON.parse(JSON.stringify(_storage.__hstorage_meta.CRC32));

		var key,
			updated = [],
			removed = [];

		for(key in oldCrc32List){
			if(oldCrc32List.hasOwnProperty(key)){
				if(!newCrc32List[key]){
					removed.push(key);
					continue;
				}
				if(oldCrc32List[key] != newCrc32List[key] && String(oldCrc32List[key]).substr(0,2) == "2."){
					updated.push(key);
				}
			}
		}

		for(key in newCrc32List){
			if(newCrc32List.hasOwnProperty(key)){
				if(!oldCrc32List[key]){
					updated.push(key);
				}
			}
		}

		_fireObservers(updated, "updated");
		_fireObservers(removed, "deleted");
	}

	/**
	 * Fires observers for updated keys
	 *
	 * @param {Array|String} keys Array of key names or a key
	 * @param {String} action What happened with the value (updated, deleted, flushed)
	 */
	function _fireObservers(keys, action){
		keys = [].concat(keys || []);
		if(action == "flushed"){
			keys = [];
			for(var key in _observers){
				if(_observers.hasOwnProperty(key)){
					keys.push(key);
				}
			}
			action = "deleted";
		}
		for(var i=0, len = keys.length; i<len; i++){
			if(_observers[keys[i]]){
				for(var j=0, jlen = _observers[keys[i]].length; j<jlen; j++){
					_observers[keys[i]][j](keys[i], action);
				}
			}
			if(_observers["*"]){
				for(var j=0, jlen = _observers["*"].length; j<jlen; j++){
					_observers["*"][j](keys[i], action);
				}
			}
		}
	}

	/**
	 * Publishes key change to listeners
	 */
	function _publishChange(){
		var updateTime = (+new Date()).toString();

		if(_backend == "localStorage" || _backend == "globalStorage"){
			try {
				_storage_service.hStorage_update = updateTime;
			} catch (E8) {
				// safari private mode has been enabled after the hStorage initialization
				_backend = false;
			}
		}else if(_backend == "userDataBehavior"){
			_storage_elm.setAttribute("hStorage_update", updateTime);
			_storage_elm.save("hStorage");
		}

		_storageObserver();
	}

	/**
	 * Loads the data from the storage based on the supported mechanism
	 */
	function _load_storage(){
		/* if hStorage string is retrieved, then decode it */
		if(_storage_service.hStorage){
			try{
				_storage = JSON.parse(String(_storage_service.hStorage));
			}catch(E6){_storage_service.hStorage = "{}";}
		}else{
			_storage_service.hStorage = "{}";
		}
		_storage_size = _storage_service.hStorage?String(_storage_service.hStorage).length:0;

		if(!_storage.__hstorage_meta){
			_storage.__hstorage_meta = {};
		}
		if(!_storage.__hstorage_meta.CRC32){
			_storage.__hstorage_meta.CRC32 = {};
		}
	}

	/**
	 * This functions provides the "save" mechanism to store the hStorage object
	 */
	function _save(){
		_dropOldEvents(); // remove expired events
		try{
			_storage_service.hStorage = JSON.stringify(_storage);
			// If userData is used as the storage engine, additional
			if(_storage_elm) {
				_storage_elm.setAttribute("hStorage",_storage_service.hStorage);
				_storage_elm.save("hStorage");
			}
			_storage_size = _storage_service.hStorage?String(_storage_service.hStorage).length:0;
		}catch(E7){/* probably cache is full, nothing is saved this way*/}
	}

	/**
	 * Function checks if a key is set and is string or numberic
	 *
	 * @param {String} key Key name
	 */
	function _checkKey(key){
		if(!key || (typeof key != "string" && typeof key != "number")){
			throw new TypeError('Key name must be string or numeric');
		}
		if(key == "__hstorage_meta"){
			throw new TypeError('Reserved key name');
		}
		return true;
	}

	/**
	 * Removes expired keys
	 */
	function _handleTTL(){
		var curtime, i, TTL, CRC32, nextExpire = Infinity, changed = false, deleted = [];

		clearTimeout(_ttl_timeout);

		if(!_storage.__hstorage_meta || typeof _storage.__hstorage_meta.TTL != "object"){
			// nothing to do here
			return;
		}

		curtime = +new Date();
		TTL = _storage.__hstorage_meta.TTL;

		CRC32 = _storage.__hstorage_meta.CRC32;
		for(i in TTL){
			if(TTL.hasOwnProperty(i)){
				if(TTL[i] <= curtime){
					delete TTL[i];
					delete CRC32[i];
					delete _storage[i];
					changed = true;
					deleted.push(i);
				}else if(TTL[i] < nextExpire){
					nextExpire = TTL[i];
				}
			}
		}

		// set next check
		if(nextExpire != Infinity){
			_ttl_timeout = setTimeout(_handleTTL, nextExpire - curtime);
		}

		// save changes
		if(changed){
			_save();
			_publishChange();
			_fireObservers(deleted, "deleted");
		}
	}

	/**
	 * Checks if there's any events on hold to be fired to listeners
	 */
	function _handlePubSub(){
		var i, len;
		if(!_storage.__hstorage_meta.PubSub){
			return;
		}
		var pubelm,
			_pubsubCurrent = _pubsub_last;

		for(i=len=_storage.__hstorage_meta.PubSub.length-1; i>=0; i--){
			pubelm = _storage.__hstorage_meta.PubSub[i];
			if(pubelm[0] > _pubsub_last){
				_pubsubCurrent = pubelm[0];
				_fireSubscribers(pubelm[1], pubelm[2]);
			}
		}

		_pubsub_last = _pubsubCurrent;
	}

	/**
	 * Fires all subscriber listeners for a pubsub channel
	 *
	 * @param {String} channel Channel name
	 * @param {Mixed} payload Payload data to deliver
	 */
	function _fireSubscribers(channel, payload){
		if(_pubsub_observers[channel]){
			for(var i=0, len = _pubsub_observers[channel].length; i<len; i++){
				// send immutable data that can't be modified by listeners
				_pubsub_observers[channel][i](channel, JSON.parse(JSON.stringify(payload)));
			}
		}
	}

	/**
	 * Remove old events from the publish stream (at least 2sec old)
	 */
	function _dropOldEvents(){
		if(!_storage.__hstorage_meta.PubSub){
			return;
		}

		var retire = +new Date() - 2000;

		for(var i=0, len = _storage.__hstorage_meta.PubSub.length; i<len; i++){
			if(_storage.__hstorage_meta.PubSub[i][0] <= retire){
				// deleteCount is needed for IE6
				_storage.__hstorage_meta.PubSub.splice(i, _storage.__hstorage_meta.PubSub.length - i);
				break;
			}
		}

		if(!_storage.__hstorage_meta.PubSub.length){
			delete _storage.__hstorage_meta.PubSub;
		}

	}

	/**
	 * Publish payload to a channel
	 *
	 * @param {String} channel Channel name
	 * @param {Mixed} payload Payload to send to the subscribers
	 */
	function _publish(channel, payload){
		if(!_storage.__hstorage_meta){
			_storage.__hstorage_meta = {};
		}
		if(!_storage.__hstorage_meta.PubSub){
			_storage.__hstorage_meta.PubSub = [];
		}

		_storage.__hstorage_meta.PubSub.unshift([+new Date, channel, payload]);

		_save();
		_publishChange();
	}


	/**
	 * JS Implementation of MurmurHash2
	 *
	 *  SOURCE: https://github.com/garycourt/murmurhash-js (MIT licensed)
	 *
	 * @author <a href="mailto:gary.court@gmail.com">Gary Court</a>
	 * @see http://github.com/garycourt/murmurhash-js
	 * @author <a href="mailto:aappleby@gmail.com">Austin Appleby</a>
	 * @see http://sites.google.com/site/murmurhash/
	 *
	 * @param {string} str ASCII only
	 * @param {number} seed Positive integer only
	 * @return {number} 32-bit positive integer hash
	 */

	function murmurhash2_32_gc(str, seed) {
		var
			l = str.length,
			h = seed ^ l,
			i = 0,
			k;

		while (l >= 4) {
			k =
			((str.charCodeAt(i) & 0xff)) |
			((str.charCodeAt(++i) & 0xff) << 8) |
			((str.charCodeAt(++i) & 0xff) << 16) |
			((str.charCodeAt(++i) & 0xff) << 24);

			k = (((k & 0xffff) * 0x5bd1e995) + ((((k >>> 16) * 0x5bd1e995) & 0xffff) << 16));
			k ^= k >>> 24;
			k = (((k & 0xffff) * 0x5bd1e995) + ((((k >>> 16) * 0x5bd1e995) & 0xffff) << 16));

			h = (((h & 0xffff) * 0x5bd1e995) + ((((h >>> 16) * 0x5bd1e995) & 0xffff) << 16)) ^ k;

			l -= 4;
			++i;
		}

		switch (l) {
			case 3: h ^= (str.charCodeAt(i + 2) & 0xff) << 16;
			case 2: h ^= (str.charCodeAt(i + 1) & 0xff) << 8;
			case 1: h ^= (str.charCodeAt(i) & 0xff);
				h = (((h & 0xffff) * 0x5bd1e995) + ((((h >>> 16) * 0x5bd1e995) & 0xffff) << 16));
		}

		h ^= h >>> 13;
		h = (((h & 0xffff) * 0x5bd1e995) + ((((h >>> 16) * 0x5bd1e995) & 0xffff) << 16));
		h ^= h >>> 15;

		return h >>> 0;
	}

	////////////////////////// PUBLIC INTERFACE /////////////////////////

	$.hStorage = {
		/* Version number */
		version: HSTORAGE_VERSION,

		/**
		 * Sets a key's value.
		 *
		 * @param {String} key Key to set. If this value is not set or not
		 *              a string an exception is raised.
		 * @param {Mixed} value Value to set. This can be any value that is JSON
		 *              compatible (Numbers, Strings, Objects etc.).
		 * @param {Object} [options] - possible options to use
		 * @param {Number} [options.TTL] - optional TTL value
		 * @return {Mixed} the used value
		 */
		set: function(key, value, options){
			_checkKey(key);

			options = options || {};

			// undefined values are deleted automatically
			if(typeof value == "undefined"){
				this.deleteKey(key);
				return value;
			}

			if(_XMLService.isXML(value)){
				value = {_is_xml:true,xml:_XMLService.encode(value)};
			}else if(typeof value == "function"){
				return undefined; // functions can't be saved!
			}else if(value && typeof value == "object"){
				// clone the object before saving to _storage tree
				value = JSON.parse(JSON.stringify(value));
			}

			_storage[key] = value;

			_storage.__hstorage_meta.CRC32[key] = "2." + murmurhash2_32_gc(JSON.stringify(value), 0x9747b28c);

			this.setTTL(key, options.TTL || 0); // also handles saving and _publishChange

			_fireObservers(key, "updated");
			return value;
		},

		/**
		 * Looks up a key in cache
		 *
		 * @param {String} key - Key to look up.
		 * @param {mixed} def - Default value to return, if key didn't exist.
		 * @param callback - Callback
		 * @return {Mixed} the key value, default value or null
		 */
		get: function(key, def, callback){
			var result = null;
			_checkKey(key);
			result = typeof(def) == 'undefined' ? null : def;
			if(key in _storage){
				if(_storage[key] && typeof _storage[key] == "object" && _storage[key]._is_xml) {
					result = _XMLService.decode(_storage[key].xml);
				}else{
					result = _storage[key];
				}
			}
			if (callback) {
				callback.call(window, result);
			} else {
				return result;
			}
		},

		/**
		 * Deletes a key from cache.
		 *
		 * @param {String} key - Key to delete.
		 * @return {Boolean} true if key existed or false if it didn't
		 */
		deleteKey: function(key){
			_checkKey(key);
			if(key in _storage){
				delete _storage[key];
				// remove from TTL list
				if(typeof _storage.__hstorage_meta.TTL == "object" &&
				   key in _storage.__hstorage_meta.TTL){
					delete _storage.__hstorage_meta.TTL[key];
				}

				delete _storage.__hstorage_meta.CRC32[key];

				_save();
				_publishChange();
				_fireObservers(key, "deleted");
				return true;
			}
			return false;
		},

		/**
		 * Sets a TTL for a key, or remove it if ttl value is 0 or below
		 *
		 * @param {String} key - key to set the TTL for
		 * @param {Number} ttl - TTL timeout in milliseconds
		 * @return {Boolean} true if key existed or false if it didn't
		 */
		setTTL: function(key, ttl){
			var curtime = +new Date();
			_checkKey(key);
			ttl = Number(ttl) || 0;
			if(key in _storage){

				if(!_storage.__hstorage_meta.TTL){
					_storage.__hstorage_meta.TTL = {};
				}

				// Set TTL value for the key
				if(ttl>0){
					_storage.__hstorage_meta.TTL[key] = curtime + ttl;
				}else{
					delete _storage.__hstorage_meta.TTL[key];
				}

				_save();

				_handleTTL();

				_publishChange();
				return true;
			}
			return false;
		},

		/**
		 * Gets remaining TTL (in milliseconds) for a key or 0 when no TTL has been set
		 *
		 * @param {String} key Key to check
		 * @return {Number} Remaining TTL in milliseconds
		 */
		getTTL: function(key){
			var curtime = +new Date(), ttl;
			_checkKey(key);
			if(key in _storage && _storage.__hstorage_meta.TTL && _storage.__hstorage_meta.TTL[key]){
				ttl = _storage.__hstorage_meta.TTL[key] - curtime;
				return ttl || 0;
			}
			return 0;
		},

		/**
		 * Deletes everything in cache.
		 *
		 * @return {Boolean} Always true
		 */
		flush: function(){
			_storage = {__hstorage_meta:{CRC32:{}}};
			_save();
			_publishChange();
			_fireObservers(null, "flushed");
			return true;
		},

		/**
		 * Returns a read-only copy of _storage
		 *
		 * @return {Object} Read-only copy of _storage
		 */
		storageObj: function(){
			function F() {}
			F.prototype = _storage;
			return new F();
		},

		/**
		 * Returns an index of all used keys as an array
		 * ['key1', 'key2',..'keyN']
		 *
		 * @return {Array} Used keys
		 */
		index: function(){
			var index = [], i;
			for(i in _storage){
				if(_storage.hasOwnProperty(i) && i != "__hstorage_meta"){
					index.push(i);
				}
			}
			return index;
		},

		/**
		 * How much space in bytes does the storage take?
		 *
		 * @return {Number} Storage size in chars (not the same as in bytes,
		 *                  since some chars may take several bytes)
		 */
		storageSize: function(){
			return _storage_size;
		},

		/**
		 * Which backend is currently in use?
		 *
		 * @return {String} Backend name
		 */
		currentBackend: function(){
			return _backend;
		},

		/**
		 * Test if storage is available
		 *
		 * @return {Boolean} True if storage can be used
		 */
		storageAvailable: function(){
			return !!_backend;
		},

		/**
		 * Register change listeners
		 *
		 * @param {String} key Key name
		 * @param {Function} callback Function to run when the key changes
		 */
		listenKeyChange: function(key, callback){
			_checkKey(key);
			if(!_observers[key]){
				_observers[key] = [];
			}
			_observers[key].push(callback);
		},

		/**
		 * Remove change listeners
		 *
		 * @param {String} key Key name to unregister listeners against
		 * @param {Function} [callback] If set, unregister the callback, if not - unregister all
		 */
		stopListening: function(key, callback){
			_checkKey(key);

			if(!_observers[key]){
				return;
			}

			if(!callback){
				delete _observers[key];
				return;
			}

			for(var i = _observers[key].length - 1; i>=0; i--){
				if(_observers[key][i] == callback){
					_observers[key].splice(i,1);
				}
			}
		},

		/**
		 * Subscribe to a Publish/Subscribe event stream
		 *
		 * @param {String} channel Channel name
		 * @param {Function} callback Function to run when the something is published to the channel
		 */
		subscribe: function(channel, callback){
			channel = (channel || "").toString();
			if(!channel){
				throw new TypeError('Channel not defined');
			}
			if(!_pubsub_observers[channel]){
				_pubsub_observers[channel] = [];
			}
			_pubsub_observers[channel].push(callback);
		},

		/**
		 * Publish data to an event stream
		 *
		 * @param {String} channel Channel name
		 * @param {Mixed} payload Payload to deliver
		 */
		publish: function(channel, payload){
			channel = (channel || "").toString();
			if(!channel){
				throw new TypeError('Channel not defined');
			}

			_publish(channel, payload);
		},

		/**
		 * Reloads the data from browser storage
		 */
		reInit: function(){
			_reloadData();
		},

		testSetup: function() {
			_init();
		},

		initHTTPSLocalStorage: function() {
			if ((typeof EXTBASE_HIJAX != 'undefined') && 'forceHTTPSLocalStorage' in EXTBASE_HIJAX && EXTBASE_HIJAX.forceHTTPSLocalStorage && window.location.href.split('://')[0]=="http") {
				var callbacks = {};
				var pendingOperations = [];
				var callId = 0;
				var hStorageFrameLoaded = false;
				var body = document.getElementsByTagName("body")[0];
				var iframe = document.createElement('iframe');
				var messageListener = function(event) {
					if (event.source === iframe || event.source === iframe.contentWindow) {
						var data = /^#localStorage#(\d+)(null)?#([\S\s]*)/.exec(event.data);
						if (data) {
							if (callbacks[data[1]]) {
								// null and "null" are distinguished by our pattern
								callbacks[data[1]](data[2] === 'null' ? null : JSON.parse(data[3]));
							}
							delete callbacks[data[1]];
						}
					}
				};
				if (!window.addEventListener) {
					window.attachEvent("onmessage", messageListener);
				}
				else {
					window.addEventListener("message", messageListener, false);
				}

				var addPendingOperation = function(method, args) {
					pendingOperations.push({method: method, args: Array.prototype.slice.call(args)});
				};

				var executePendingOperations = function() {
					var op;

					while (op = pendingOperations.shift()) {
						$.hStorage[op.method].apply(null, op.args);
					}
				};

				$.hStorage.set = function(key, value) {
					if (!hStorageFrameLoaded) {
						addPendingOperation('set', arguments);
					} else {
						var obj = {
							setItem: key,
							value: value
						};
						if (typeof iframe.postMessage === "function" || typeof iframe.postMessage === "object") {
							iframe.postMessage(JSON.stringify(obj), 'https://' + window.location.hostname);
						} else {
							iframe.contentWindow.postMessage(JSON.stringify(obj), 'https://' + window.location.hostname);
						}
					}
				};

				$.hStorage.get = function(key, def, callback) {
					if (!hStorageFrameLoaded) {
						addPendingOperation('get', arguments);
					} else {
						var identifier = callId++;
						var obj = {
							identifier: identifier,
							def: def,
							getItem: key
						};
						callbacks[identifier] = callback;
						if (typeof iframe.postMessage === "function" || typeof iframe.postMessage === "object") {
							iframe.postMessage(JSON.stringify(obj), 'https://' + window.location.hostname);
						} else {
							iframe.contentWindow.postMessage(JSON.stringify(obj), 'https://' + window.location.hostname);
						}
					}
				};

				$.hStorage.deleteKey = function(key) {
					if (!hStorageFrameLoaded) {
						addPendingOperation('deleteKey', arguments);
					} else {
						var obj = {
							removeItem: key
						};
						if (typeof iframe.postMessage === "function" || typeof iframe.postMessage === "object") {
							iframe.postMessage(JSON.stringify(obj), 'https://' + window.location.hostname);
						} else {
							iframe.contentWindow.postMessage(JSON.stringify(obj), 'https://' + window.location.hostname);
						}
					}
				};

				iframe.src = 'https://' + window.location.hostname + '/index.php?eID=extbase_hijax_dispatcher&r[0][secureLocalStorage]=1';
				// iframe.id = 'hStorageFrame';
				iframe.style.display = 'none';

				if (navigator.userAgent.indexOf("MSIE") > -1 && !window.opera) {
					iframe.onreadystatechange = function(){
						if (iframe.readyState == "complete"){
							hStorageFrameLoaded = true;
							executePendingOperations();
						}
					};
				} else {
					iframe.onload = function(){
						hStorageFrameLoaded = true;
						executePendingOperations();
					};
				}

				body.appendChild(iframe);
			}
		}
	};

	// Initialize hStorage
	_init();
})(jQuery);

$.hStorage.initHTTPSLocalStorage.call(window);