import './bootstrap';
import Alpine from 'alpinejs';

// Import window components
import { windowManager } from './desktop/window-manager';
import { terminal } from './desktop/terminal';
import { fileExplorer } from './desktop/file-explorer';
import { nodeManager } from './desktop/node-manager';
import { browser } from './desktop/browser';
import { messages } from './desktop/messages';
import { sentinel } from './desktop/sentinel';
import { secureChannel } from './desktop/secure-channel';
import { firewall } from './desktop/firewall';
import { jobsBoard } from './desktop/jobs-board';

// Register Alpine data components
Alpine.data('windowManager', windowManager);
Alpine.data('terminal', terminal);
Alpine.data('fileExplorer', fileExplorer);
Alpine.data('nodeManager', nodeManager);
Alpine.data('browser', browser);
Alpine.data('messages', messages);
Alpine.data('sentinel', sentinel);
Alpine.data('secureChannel', secureChannel);
Alpine.data('firewall', firewall);
Alpine.data('jobsBoard', jobsBoard);

// Start Alpine
Alpine.start();

window.Alpine = Alpine;
