<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '8.0.20',
    ],
    '@symfony/ux-live-component' => [
        'path' => './vendor/symfony/ux-live-component/assets/dist/live_controller.js',
    ],
    'echarts/dist/echarts.js' => [
        'version' => '6.0.0',
    ],
    'flowbite' => [
        'version' => '3.1.2',
    ],
    'tslib' => [
        'version' => '2.3.0',
    ],
    'zrender/lib/zrender.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/core/util.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/core/env.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/core/timsort.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/core/Eventful.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/Text.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/tool/color.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/Path.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/tool/path.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/core/matrix.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/core/vector.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/core/Transformable.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/Image.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/Group.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/shape/Circle.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/shape/Ellipse.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/shape/Sector.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/shape/Ring.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/shape/Polygon.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/shape/Polyline.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/shape/Rect.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/shape/Line.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/shape/BezierCurve.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/shape/Arc.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/CompoundPath.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/LinearGradient.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/RadialGradient.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/core/BoundingRect.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/core/OrientedBoundingRect.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/core/Point.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/IncrementalDisplayable.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/helper/subPixelOptimize.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/core/dom.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/helper/parseText.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/core/WeakMap.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/core/LRU.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/contain/text.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/canvas/graphic.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/core/platform.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/contain/polygon.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/core/PathProxy.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/contain/util.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/core/curve.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/svg/Painter.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/canvas/Painter.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/core/event.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/tool/parseSVG.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/tool/parseXML.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/graphic/Displayable.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/core/bbox.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/contain/line.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/contain/quadratic.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/animation/Animator.js' => [
        'version' => '6.0.0',
    ],
    'zrender/lib/tool/morphPath.js' => [
        'version' => '6.0.0',
    ],
    '@popperjs/core' => [
        'version' => '2.11.8',
    ],
    'flowbite-datepicker' => [
        'version' => '1.3.2',
    ],
    'flowbite/dist/flowbite.min.css' => [
        'version' => '3.1.2',
        'type' => 'css',
    ],
    'apexcharts' => [
        'version' => '5.3.5',
    ],
];
