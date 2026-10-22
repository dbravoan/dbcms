import { createAiWorkbench } from './workbench.js';
import { mediaBlockActions } from './media-blocks.js';

export default function registerAiAssistant(Alpine) {
    Alpine.data('aiWorkbench', createAiWorkbench);
    window.cmsAiMediaActions = mediaBlockActions;
}
