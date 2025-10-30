/*
 * FCKeditor - The text editor for internet
 * Copyright (C) 2003-2005 Frederico Caldeira Knabben
 *
 * Licensed under the terms of the GNU Lesser General Public License:
 *              http://www.opensource.org/licenses/lgpl-license.php
 *
 * For further information visit:
 *              http://www.fckeditor.net/
 *
 * File Name: fckxml.js
 *      Defines the FCKXml object that is used for XML data calls
 *      and XML processing.
 *      This script is shared by almost all pages that compose the
 *      File Browser frameset.
 *
 * File Authors:
 *              Frederico Caldeira Knabben (fredck@fckeditor.net)
 */

(() => {
    'use strict';

    const globalScope = window;

    const escapeHTML = (text = '') =>
        text
            .replace(/\n/g, '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

    class FCKXml {
        constructor() {
            this.DOMDocument = null;
        }

        GetHttpRequest() {
            return new XMLHttpRequest();
        }

        LoadUrl(urlToCall, asyncFunctionPointer) {
            const isAsync = typeof asyncFunctionPointer === 'function';
            const request = this.GetHttpRequest();

            request.open('GET', urlToCall, isAsync);

            if (isAsync) {
                request.onreadystatechange = () => {
                    if (request.readyState !== 4) {
                        return;
                    }

                    if (request.status === 200) {
                        this.DOMDocument = request.responseXML;

                        if (!this.DOMDocument) {
                            console.error(`Failed to load XML document from ${urlToCall}`);
                        }

                        asyncFunctionPointer(this);
                    } else {
                        console.error(`Failed to load URL: ${urlToCall} Status: ${request.status}`);
                    }
                };

                request.send(null);
                return undefined;
            }

            request.send(null);

            if (request.status === 200) {
                this.DOMDocument = request.responseXML;

                if (!this.DOMDocument) {
                    console.error(`Failed to load XML document from ${urlToCall}`);
                }
            } else {
                console.error(`Failed to load URL: ${urlToCall} Status: ${request.status}`);
            }

            return undefined;
        }

        SelectNodes(xpath) {
            if (!this.DOMDocument) {
                console.error('DOMDocument is null');
                return [];
            }

            try {
                const resolver = this.DOMDocument.createNSResolver(
                    this.DOMDocument.documentElement
                );
                const xPathResult = this.DOMDocument.evaluate(
                    xpath,
                    this.DOMDocument,
                    resolver,
                    XPathResult.ORDERED_NODE_ITERATOR_TYPE,
                    null
                );

                if (!xPathResult) {
                    return [];
                }

                const nodes = [];
                let node = xPathResult.iterateNext();

                while (node) {
                    nodes.push(node);
                    node = xPathResult.iterateNext();
                }

                return nodes;
            } catch (error) {
                console.error('Error evaluating XPath:', error);
                return [];
            }
        }

        SelectSingleNode(xpath) {
            if (!this.DOMDocument) {
                console.error('DOMDocument is null');
                return null;
            }

            try {
                const resolver = this.DOMDocument.createNSResolver(
                    this.DOMDocument.documentElement
                );
                const xPathResult = this.DOMDocument.evaluate(
                    xpath,
                    this.DOMDocument,
                    resolver,
                    XPathResult.FIRST_ORDERED_NODE_TYPE,
                    null
                );

                return xPathResult.singleNodeValue;
            } catch (error) {
                console.error('Error evaluating XPath:', error);
                return null;
            }
        }
    }

    globalScope.escapeHTML = escapeHTML;
    globalScope.FCKXml = FCKXml;
})();
