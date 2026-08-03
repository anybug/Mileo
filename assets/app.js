/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';
import './styles/edit-report.css';

// start the Stimulus application
import 'chart.js';
import './script.js';
import './js/calendar.js';

import { startStimulusApp } from '@symfony/stimulus-bundle';

import QuillEditorController from './controllers/quill_editor_controller.js';
import VehiculePaginationController from './controllers/vehicule_pagination_controller.js';

const app = startStimulusApp();

app.register('quill-editor', QuillEditorController);
app.register('vehicule-pagination', VehiculePaginationController);