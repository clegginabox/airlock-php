import '../css/app.css';

import Prism from 'prismjs';

// Prism language components expect window.Prism to exist
window.Prism = Prism;

import('prismjs/components/prism-markup')
    .then(() => import('prismjs/components/prism-markup-templating'))
    .then(() => import('prismjs/components/prism-php'))
    .then(() => import('prismjs/components/prism-php-extras'))
    .then(() => Prism.highlightAll());
