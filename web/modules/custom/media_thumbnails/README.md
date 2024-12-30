# Media Thumbnails

## Introduction

Provides a plugin type for custom media entity thumbnails.

This module allows developers to create custom media entity thumbnails
using plugins.

Sample use cases:

* provide thumbnails for file types unsupported by core,
  like PDF, SVG, ePub, Word or Excel
* create variants of thumbnails, e.g. watermarked thumbnails for media entities
* provide custom generic thumbnails, e.g. configurable in the UI
* store thumbnails in a custom location, e.g. use public thumbnails
  for private media entities

## Installation

Install this module as usual, and enable at least one
thumbnail plugin module, e.g. the bundled
Media Thumbnails PDF module. See below for more options.

## Removal

Uninstall all thumbnail plugin modules. Optionally run the
refresh batch operation (see below) to restore the default
media thumbnails. At last, uninstall this module.

## Configuration

The configuration page (/admin/config/media/thumbnails) allows
specifying a maximum thumbnail width.
There's also a form for running a batch operation
for thumbnail regeneration.
Custom plugins might provide their own configuration pages.

## API

Plugins can be configured (with annotations) per media file mime type.

Example:

```
* @MediaThumbnail(
*   id = "media_thumbnail_pdf",
*   label = @Translation("Media Thumbnail PDF"),
*   mime = {
*     "application/pdf"
*   }
* )
```

The plugin should implement a method ```createThumbnail($sourceUri)```.
The uri of the local file (media source) is passed to this method.
The plugin should return a (new or existent) managed thumbnail file object.

All media entity related stuff will be handled by the plugin manager.

## Example

The Media Thumbnails PDF module (modules/media_thumbnails_pdf) contains
a ready to use plugin. It provides thumbnails for media entities of
mime type application/pdf.

## Modules providing thumbnail plugins

* Media Thumbnails PDF (bundled)
* [Media Thumbnails EPUB](https://www.drupal.org/project/media_thumbnails_epub)
* [Media Thumbnails SVG](https://www.drupal.org/project/media_thumbnails_svg)
* [Media Thumbnails Video](https://www.drupal.org/project/media_thumbnails_video)
  by [Szczepan Musial](https://www.drupal.org/u/lamp5)
