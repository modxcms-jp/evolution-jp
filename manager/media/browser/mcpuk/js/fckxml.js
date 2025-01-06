/*
 * FCKeditor - The text editor for internet
 * Copyright (C) 2003-2005 Frederico Caldeira Knabben
 *
 * Licensed under the terms of the GNU Lesser General Public License:
 * 		http://www.opensource.org/licenses/lgpl-license.php
 *
 * For further information visit:
 * 		http://www.fckeditor.net/
 *
 * File Name: fckxml.js
 * 	Defines the FCKXml object that is used for XML data calls
 * 	and XML processing.
 * 	This script is shared by almost all pages that compose the
 * 	File Browser frameset.
 *
 * File Authors:
 * 		Frederico Caldeira Knabben (fredck@fckeditor.net)
 */

var FCKXml = function () {};

function escapeHTML(text) {
  text = text.replace('\n', '');
  text = text.replace('&', '&amp;');
  text = text.replace('<', '&lt;');
  text = text.replace('>', '&gt;');
  return text;
}

FCKXml.prototype.GetHttpRequest = function () {
  return new XMLHttpRequest();
};

FCKXml.prototype.LoadUrl = function (urlToCall, asyncFunctionPointer) {
  var oFCKXml = this;

  var bAsync = typeof asyncFunctionPointer == 'function';

  var oXmlHttp = this.GetHttpRequest();
  oXmlHttp.open('GET', urlToCall, bAsync);

  if (bAsync) {
    oXmlHttp.onreadystatechange = function () {
      if (oXmlHttp.readyState == 4) {
        if (oXmlHttp.status == 200) {
          oFCKXml.DOMDocument = oXmlHttp.responseXML;
          if (oFCKXml.DOMDocument == null) {
            console.error('Failed to load XML document from ' + urlToCall);
          }
          asyncFunctionPointer(oFCKXml);
        } else {
          console.error(
            'Failed to load URL: ' + urlToCall + ' Status: ' + oXmlHttp.status
          );
        }
      }
    };
  }

  oXmlHttp.send(null);

  if (!bAsync) {
    if (oXmlHttp.status == 200) {
      this.DOMDocument = oXmlHttp.responseXML;
      if (this.DOMDocument == null) {
        console.error('Failed to load XML document from ' + urlToCall);
      }
    } else {
      console.error(
        'Failed to load URL: ' + urlToCall + ' Status: ' + oXmlHttp.status
      );
    }
  }
};

FCKXml.prototype.SelectNodes = function (xpath) {
  if (this.DOMDocument == null) {
    console.error('DOMDocument is null');
    return [];
  }

  var nodes = [];
  try {
    var xPathResult = this.DOMDocument.evaluate(
      xpath,
      this.DOMDocument,
      this.DOMDocument.createNSResolver(this.DOMDocument.documentElement),
      XPathResult.ORDERED_NODE_ITERATOR_TYPE,
      null
    );
    if (xPathResult) {
      var oNode = xPathResult.iterateNext();
      while (oNode) {
        nodes[nodes.length] = oNode;
        oNode = xPathResult.iterateNext();
      }
    }
  } catch (e) {
    console.error('Error evaluating XPath:', e);
  }
  return nodes;
};

FCKXml.prototype.SelectSingleNode = function (xpath) {
  if (this.DOMDocument == null) {
    console.error('DOMDocument is null');
    return null;
  }

  try {
    var xPathResult = this.DOMDocument.evaluate(
      xpath,
      this.DOMDocument,
      this.DOMDocument.createNSResolver(this.DOMDocument.documentElement),
      XPathResult.FIRST_ORDERED_NODE_TYPE,
      null
    );
    return xPathResult.singleNodeValue;
  } catch (e) {
    console.error('Error evaluating XPath:', e);
    return null;
  }
};
