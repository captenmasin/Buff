import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const onboardingSource = readFileSync(new URL('../resources/js/Pages/Onboarding.vue', import.meta.url), 'utf8');
const approvalSource = readFileSync(new URL('../resources/js/Pages/McpApproval.vue', import.meta.url), 'utf8');
const addSource = readFileSync(new URL('../resources/js/Pages/Add.vue', import.meta.url), 'utf8');
const progressSource = readFileSync(new URL('../resources/js/Pages/Progress.vue', import.meta.url), 'utf8');
const recipeSource = readFileSync(new URL('../resources/js/Components/RecipeMode.vue', import.meta.url), 'utf8');
const assistantsSource = readFileSync(new URL('../resources/js/Pages/Settings/ConnectedAssistants.vue', import.meta.url), 'utf8');
const appStyles = readFileSync(new URL('../resources/css/app.css', import.meta.url), 'utf8');

test('reveals the generated onboarding plan with restrained reduced-motion feedback', () => {
    assert.match(onboardingSource, /<Transition[\s\S]*?<div v-if="planLoading" key="loading"[\s\S]*?<div v-else key="summary"/);
    assert.match(onboardingSource, /enter-from-class="scale-\[0\.97\] opacity-0 motion-reduce:scale-100"/);
    assert.match(onboardingSource, /motion-reduce:duration-150 motion-reduce:transition-opacity/);
});

test('bridges assistant approval into its keyed success state', () => {
    assert.match(approvalSource, /<Transition[\s\S]*?<div v-if="approved \|\| approval\?\.status === 'approved'" key="approved"[\s\S]*?<div v-else-if="approval" key="pending"/);
    assert.match(approvalSource, /leave-to-class="scale-\[0\.97\] opacity-0 motion-reduce:scale-100"/);
});

test('presents both full-screen cameras through the same drawer path', () => {
    assert.match(addSource, /<Transition[\s\S]*?enter-from-class="translate-y-full opacity-0 motion-reduce:translate-y-0"[\s\S]*?<div v-if="webScannerOpen"/);
    assert.match(progressSource, /<Teleport to="body">[\s\S]*?<Transition[\s\S]*?leave-to-class="translate-y-full opacity-0 motion-reduce:translate-y-0"[\s\S]*?<div v-if="cameraOpen"/);
    assert.match(addSource, /duration-sheet ease-drawer motion-reduce:duration-150 motion-reduce:transition-opacity/);
});

test('uses directional motion when entering and leaving recipe modes', () => {
    assert.match(recipeSource, /const transitionDirection = ref<'forward' \| 'back'>\('forward'\);/);
    assert.match(recipeSource, /function returnToRecipes\(\): void \{[\s\S]*?transitionDirection\.value = 'back';/);
    assert.match(recipeSource, /:enter-from-class="transitionDirection === 'forward'[\s\S]*?translate-x-3 opacity-0[\s\S]*?:leave-to-class="transitionDirection === 'forward'/);
});

test('animates assistant and photo collection changes without staggering', () => {
    assert.match(assistantsSource, /<TransitionGroup[\s\S]*?<Card v-if="authorizedConnections\.length === 0" key="empty"[\s\S]*?<Card v-for="connection in authorizedConnections"/);
    assert.match(addSource, /<TransitionGroup[\s\S]*?v-if="selectedPhotos\.length \|\| photoProcessing"[\s\S]*?appear[\s\S]*?<div[\s\S]*?v-for="\(photo, index\) in selectedPhotos"/);
    assert.match(addSource, /<div v-if="photoProcessing" key="processing"/);
    assert.doesNotMatch(addSource, /delay-(?:30|50|75|100)/);
});

test('reduces in-app motion to a short opacity transition', () => {
    assert.match(appStyles, /html\[data-reduce-motion\] \[data-motion-transform\] \{[\s\S]*?transform: none !important;[\s\S]*?transition-duration: 150ms !important;[\s\S]*?transition-property: opacity !important;/);
    assert.match(onboardingSource, /key="summary" data-motion-transform/);
    assert.match(progressSource, /v-if="cameraOpen" data-motion-transform/);
});
