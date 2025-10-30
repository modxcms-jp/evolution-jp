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
 * File Name: common.js
 *      Common objects and functions shared by all pages that compose the
 *      File Browser dialog window.
 *
 * File Authors:
 *              Frederico Caldeira Knabben (fredck@fckeditor.net)
 */

(() => {
    'use strict';

    const globalScope = window;

    const addSelectOption = (
        selectElement,
        optionText,
        optionValue,
        isSelected = false
    ) => {
        const option = new Option(optionText, optionValue, false, Boolean(isSelected));
        selectElement.add(option);
        return option;
    };

    const safeGetSearch = (targetWindow) => {
        if (!targetWindow) {
            return '';
        }

        try {
            return targetWindow.location?.search ?? '';
        } catch (error) {
            console.warn('Unable to read window search parameters.', error);
            return '';
        }
    };

    const getParamFromSearch = (search, paramName) => {
        if (!search) {
            return '';
        }

        const params = new URLSearchParams(search);
        return params.get(paramName) ?? '';
    };

    const getUrlParam = (paramName) => {
        const topSearch = safeGetSearch(globalScope.top) || safeGetSearch(globalScope);
        return getParamFromSearch(topSearch, paramName);
    };

    const getMyUrlParam = (paramName) => getParamFromSearch(safeGetSearch(globalScope), paramName);

    class Connector {
        constructor({
            currentFolder = '/',
            uploadHandler = '',
            connectorUrl,
            resourceType = '',
            extraParams = '',
            editor = ''
        }) {
            this.CurrentFolder = currentFolder;
            this.ConnectorUrl = connectorUrl;
            this.ResourceType = resourceType;
            this.ExtraParams = extraParams;
            this.Editor = editor;
            this.UploadHandler =
                !uploadHandler || uploadHandler === 'undefined'
                    ? connectorUrl
                    : uploadHandler;
        }

        buildBaseUrl(command) {
            const query = new URLSearchParams({
                Command: command,
                Type: this.ResourceType,
                ExtraParams: this.ExtraParams,
                CurrentFolder: this.CurrentFolder,
                editor: this.Editor
            });

            return `${this.ConnectorUrl}?${query.toString()}`;
        }

        SendCommand(command, params, callBackFunction) {
            // Build base parameters
            const baseParams = {
                Command: command,
                Type: this.ResourceType,
                ExtraParams: this.ExtraParams,
                CurrentFolder: this.CurrentFolder,
                editor: this.Editor
            };

            // Merge additional params
            let mergedParams = { ...baseParams };
            if (params) {
                if (typeof params === 'string') {
                    // Parse string params into object
                    const searchParams = new URLSearchParams(params);
                    for (const [key, value] of searchParams.entries()) {
                        mergedParams[key] = value;
                    }
                } else if (typeof params === 'object') {
                    mergedParams = { ...mergedParams, ...params };
                }
            }

            const query = new URLSearchParams(mergedParams);
            const url = `${this.ConnectorUrl}?${query.toString()}`;
            const xml = new FCKXml();

            if (typeof callBackFunction === 'function') {
                xml.LoadUrl(url, callBackFunction);
                return undefined;
            }

            return xml.LoadUrl(url);
        }
    }

    const pathname = globalScope.location.pathname.replace(
        /manager\/media\/browser\/mcpuk\/.*$/,
        ''
    );

    const connector = new Connector({
        currentFolder: '/',
        uploadHandler: getUrlParam('UploadHandler'),
        connectorUrl: `${pathname}manager/media/browser/mcpuk/connectors/connector.php`,
        resourceType: getUrlParam('Type'),
        extraParams: getUrlParam('ExtraParams'),
        editor: getUrlParam('editor')
    });

    const availableIconsArray = [
        'ai',
        'avi',
        'bmp',
        'cs',
        'dll',
        'doc',
        'exe',
        'fla',
        'gif',
        'htm',
        'html',
        'jpg',
        'js',
        'mdb',
        'mp3',
        'pdf',
        'ppt',
        'rdp',
        'swf',
        'swt',
        'txt',
        'vsd',
        'xls',
        'xml',
        'zip'
    ];

    const availableIcons = availableIconsArray.reduce((iconMap, icon) => {
        iconMap[icon] = true;
        return iconMap;
    }, {});

    const getIcon = (fileName) => {
        const lastDotIndex = fileName.lastIndexOf('.');
        const extension = lastDotIndex === -1 ? '' : fileName.substring(lastDotIndex + 1).toLowerCase();
        return availableIcons[extension] ? extension : 'default.icon';
    };

    globalScope.AddSelectOption = addSelectOption;
    globalScope.GetUrlParam = getUrlParam;
    globalScope.GetMyUrlParam = getMyUrlParam;
    globalScope.oConnector = connector;
    globalScope.oIcons = {
        AvailableIconsArray: availableIconsArray,
        AvailableIcons: availableIcons,
        GetIcon: getIcon
    };
})();
