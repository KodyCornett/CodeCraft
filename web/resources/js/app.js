import './bootstrap';
import Alpine from 'alpinejs';

// Import window components
import { windowManager } from './desktop/window-manager';
import { terminal } from './desktop/terminal';
import { fileExplorer } from './desktop/file-explorer';
import { nodeManager } from './desktop/node-manager';

// Register Alpine data components
Alpine.data('windowManager', windowManager);
Alpine.data('terminal', terminal);
Alpine.data('fileExplorer', fileExplorer);
Alpine.data('nodeManager', nodeManager);

// Start Alpine
Alpine.start();

window.Alpine = Alpine;
