<script setup lang="ts">
import axios from 'axios';
import { computed, onUnmounted, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2, UtensilsCrossed } from '@lucide/vue';
import {foodSearchUrl, responseErrorMessage} from '../foodRequests';
import Card from './Card.vue';
import ConfirmSheet from './ConfirmSheet.vue';
import MealTypePicker from './Add/MealTypePicker.vue';
import Button from './ui/button/Button.vue';
import Input from './ui/input/Input.vue';
import Select from './ui/select/Select.vue';
import SelectContent from './ui/select/SelectContent.vue';
import SelectItem from './ui/select/SelectItem.vue';
import SelectTrigger from './ui/select/SelectTrigger.vue';
import SelectValue from './ui/select/SelectValue.vue';

type MealType = 'breakfast' | 'lunch' | 'dinner' | 'snacks';

interface RecipeItem {
    name: string;
    food_product_id: string | null;
    portion_quantity: number;
    portion_unit: 'g' | 'ml';
    calories: number;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
}

interface Recipe {
    id: string;
    name: string;
    servings: number;
    calories: number;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
    items: RecipeItem[];
}

interface FoodProduct {
    id: string;
    name: string;
    brand?: string | null;
    calories_per_100: number;
    protein_per_100: number;
    carbs_per_100: number;
    fat_per_100: number;
    nutrition_unit?: string | null;
}

const props = withDefaults(defineProps<{
    date: string;
    mealTypes: MealType[];
    recipes: Recipe[];
    meal?: MealType | null;
}>(), {
    meal: null,
});

const creating = ref(false);
const editingRecipe = ref<Recipe | null>(null);
const selectedRecipe = ref<Recipe | null>(null);
const transitionDirection = ref<'forward' | 'back'>('forward');
const pendingRecipeDelete = ref<Recipe | null>(null);
const recipeDeleteError = ref('');
const searchQuery = ref('');
const searchResults = ref<FoodProduct[]>([]);
const searchError = ref('');
const searchLoading = ref(false);
const customItemError = ref('');
let searchRequest = 0;
let searchTimer = 0;

const recipeForm = useForm({
    date: props.date,
    name: '',
    servings: 1,
    items: [] as RecipeItem[],
});

const logForm = useForm({
    date: props.date,
    meal_type: props.meal || smartMealType(),
    recipe_id: '',
    servings: 1,
});

const customItem = ref(newCustomItem());

const recipeTotals = computed(() => recipeForm.items.reduce((totals, item) => ({
    calories: totals.calories + item.calories,
    protein_g: totals.protein_g + item.protein_g,
    carbs_g: totals.carbs_g + item.carbs_g,
    fat_g: totals.fat_g + item.fat_g,
}), { calories: 0, protein_g: 0, carbs_g: 0, fat_g: 0 }));

function macrosFor(caloriesPer100: number, protein: number, carbs: number, fat: number, quantity: number) {
    const factor = Math.max(quantity, 0) / 100;

    return {
        calories: Math.round(caloriesPer100 * factor),
        protein_g: round(protein * factor),
        carbs_g: round(carbs * factor),
        fat_g: round(fat * factor),
    };
}

function round(value: number): number {
    return Math.round(value * 100) / 100;
}

function customCalories(): number {
    return Math.round((Number(customItem.value.protein_g) * 4) + (Number(customItem.value.carbs_g) * 4) + (Number(customItem.value.fat_g) * 9));
}

function newCustomItem(): Omit<RecipeItem, 'food_product_id' | 'calories'> {
    return {
        name: '',
        portion_quantity: 100,
        portion_unit: 'g',
        protein_g: 0,
        carbs_g: 0,
        fat_g: 0,
    };
}

function resetFoodSearch(): void {
    window.clearTimeout(searchTimer);
    searchTimer = 0;
    searchRequest++;
    searchQuery.value = '';
    searchResults.value = [];
    searchError.value = '';
    searchLoading.value = false;
}

function resetRecipeEditor(): void {
    recipeForm.resetAndClearErrors();
    editingRecipe.value = null;
    customItem.value = newCustomItem();
    customItemError.value = '';
    resetFoodSearch();
}

function startCreate(): void {
    resetRecipeEditor();
    transitionDirection.value = 'forward';
    creating.value = true;
    selectedRecipe.value = null;
}

function startEdit(recipe: Recipe): void {
    resetRecipeEditor();
    transitionDirection.value = 'forward';
    creating.value = true;
    selectedRecipe.value = null;
    editingRecipe.value = recipe;
    recipeForm.name = recipe.name;
    recipeForm.servings = recipe.servings;
    recipeForm.items = recipe.items.map((item) => ({...item}));
}

function startLog(recipe: Recipe): void {
    transitionDirection.value = 'forward';
    selectedRecipe.value = recipe;
    creating.value = false;
    logForm.recipe_id = recipe.id;
    logForm.servings = recipe.servings;
    logForm.meal_type = props.meal || smartMealType();
    logForm.clearErrors();
}

function returnToRecipes(): void {
    transitionDirection.value = 'back';
    creating.value = false;
    selectedRecipe.value = null;
    logForm.clearErrors();
    resetRecipeEditor();
}

function smartMealType(): MealType {
    const hour = new Date().getHours();

    if (hour < 10) return 'breakfast';
    if (hour < 14) return 'lunch';
    if (hour < 20) return 'dinner';

    return 'snacks';
}

function addCustomItem(): void {
    const name = customItem.value.name.trim();
    const portionQuantity = customItem.value.portion_quantity;
    const macroValues = [customItem.value.protein_g, customItem.value.carbs_g, customItem.value.fat_g];

    customItemError.value = '';

    if (!name) {
        customItemError.value = 'Enter an ingredient name.';

        return;
    }

    if (name.length > 120) {
        customItemError.value = 'Ingredient names must be 120 characters or fewer.';

        return;
    }

    if (!Number.isFinite(portionQuantity) || portionQuantity < 0.1 || portionQuantity > 10000) {
        customItemError.value = 'Amount must be between 0.1 and 10,000 g or ml.';

        return;
    }

    if (macroValues.some((value) => !Number.isFinite(value) || value < 0 || value > 1000)) {
        customItemError.value = 'Protein, carbs, and fat must each be between 0 and 1,000 g.';

        return;
    }

    recipeForm.items.push({
        name,
        food_product_id: null,
        portion_quantity: portionQuantity,
        portion_unit: customItem.value.portion_unit,
        calories: customCalories(),
        protein_g: macroValues[0],
        carbs_g: macroValues[1],
        fat_g: macroValues[2],
    });
    customItem.value = newCustomItem();
}

function addProduct(product: FoodProduct): void {
    const unit = product.nutrition_unit === 'ml' ? 'ml' : 'g';
    recipeForm.items.push({
        name: product.name,
        food_product_id: product.id,
        portion_quantity: 100,
        portion_unit: unit,
        ...macrosFor(product.calories_per_100, product.protein_per_100, product.carbs_per_100, product.fat_per_100, 100),
    });
    resetFoodSearch();
}

function removeItem(index: number): void {
    recipeForm.items.splice(index, 1);
}

function recipeItemErrors(index: number): string[] {
    const prefix = `items.${index}.`;

    return Object.entries(recipeForm.errors)
        .filter(([field]) => field.startsWith(prefix))
        .map(([, error]) => error);
}

function queueFoodSearch(): void {
    window.clearTimeout(searchTimer);

    const query = searchQuery.value.trim();
    const request = ++searchRequest;

    searchError.value = '';

    if (query.length < 2) {
        searchResults.value = [];
        searchLoading.value = false;

        return;
    }

    searchLoading.value = true;
    searchTimer = window.setTimeout(() => void searchFoods(query, request), 250);
}

function retryFoodSearch(): void {
    window.clearTimeout(searchTimer);

    const query = searchQuery.value.trim();
    const request = ++searchRequest;

    void searchFoods(query, request);
}

async function searchFoods(query: string, request: number): Promise<void> {
    searchError.value = '';

    if (query.length < 2) {
        searchResults.value = [];
        searchLoading.value = false;

        return;
    }

    searchLoading.value = true;

    try {
        const {data} = await axios.get(foodSearchUrl(query, navigator.language));

        if (request === searchRequest) {
            searchResults.value = (data.products || []).filter((result: { type?: string }) => result.type !== 'previous_meal');
        }
    } catch (error) {
        if (request === searchRequest) {
            searchResults.value = [];
            searchError.value = responseErrorMessage(error, 'q', 'Food search is unavailable. Check your connection and try again.');
        }
    } finally {
        if (request === searchRequest) {
            searchLoading.value = false;
        }
    }
}

onUnmounted(() => {
    window.clearTimeout(searchTimer);
    searchRequest++;
});

function saveRecipe(): void {
    const options = {
        preserveScroll: true,
        onSuccess: returnToRecipes,
    };

    if (editingRecipe.value) {
        recipeForm.put(`/recipes/${editingRecipe.value.id}`, options);

        return;
    }

    recipeForm.post('/recipes', options);
}

function logRecipe(): void {
    logForm.post('/meals/recipe');
}

function requestRecipeDelete(recipe: Recipe): void {
    recipeDeleteError.value = '';
    pendingRecipeDelete.value = recipe;
}
function cancelRecipeDelete(): void {
    if (logForm.processing) {
        return;
    }

    recipeDeleteError.value = '';
    pendingRecipeDelete.value = null;
}
function confirmRecipeDelete(): void {
    const recipe = pendingRecipeDelete.value;

    if (!recipe || logForm.processing) {
        return;
    }

    recipeDeleteError.value = '';
    logForm.delete(`/recipes/${recipe.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            pendingRecipeDelete.value = null;
        },
        onError: () => {
            recipeDeleteError.value = 'Couldn’t delete this recipe. Try again.';
        },
        onFinish: () => {
            if (pendingRecipeDelete.value && !recipeDeleteError.value) {
                recipeDeleteError.value = 'Couldn’t delete this recipe. Try again.';
            }
        },
    });
}
</script>

<template>
    <div class="space-y-4">
        <Transition
            mode="out-in"
            enter-active-class="transition-[opacity,transform] duration-200 ease-out motion-reduce:duration-150 motion-reduce:transition-opacity"
            :enter-from-class="transitionDirection === 'forward'
                ? 'translate-x-3 opacity-0 motion-reduce:translate-x-0'
                : '-translate-x-3 opacity-0 motion-reduce:translate-x-0'"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition-[opacity,transform] duration-150 ease-in-out motion-reduce:transition-opacity"
            leave-from-class="translate-x-0 opacity-100"
            :leave-to-class="transitionDirection === 'forward'
                ? '-translate-x-3 opacity-0 motion-reduce:translate-x-0'
                : 'translate-x-3 opacity-0 motion-reduce:translate-x-0'"
        >
        <Card v-if="creating" key="create" data-motion-transform>
            <div class="flex items-center gap-2">
                <UtensilsCrossed :size="21" class="text-food" />
                <h2 class="card-title">{{ editingRecipe ? 'Edit recipe' : 'New recipe' }}</h2>
            </div>
            <form class="mt-4 space-y-3" @submit.prevent="saveRecipe">
                <label class="block">
                    <span class="field-label">Name</span>
                    <Input v-model="recipeForm.name" class="mt-1" />
                    <span v-if="recipeForm.errors.name" class="mt-1 block text-sm text-destructive">{{ recipeForm.errors.name }}</span>
                </label>
                <label class="block">
                    <span class="field-label">Servings</span>
                    <Input v-model.number="recipeForm.servings" type="number" min="0.1" step="0.1" class="mt-1" />
                    <span v-if="recipeForm.errors.servings" class="mt-1 block text-sm text-destructive" role="alert">{{ recipeForm.errors.servings }}</span>
                </label>

                <div v-if="recipeForm.items.length" class="divide-y divide-border/60 rounded-xl bg-muted/60 px-3">
                    <div v-for="(item, index) in recipeForm.items" :key="`${item.name}-${index}`" class="flex items-center gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ item.name }}</p>
                            <p class="text-sm text-muted-foreground">{{ item.portion_quantity }}{{ item.portion_unit }} · {{ item.calories }} kcal</p>
                            <div v-if="recipeItemErrors(index).length" class="mt-1 text-sm text-destructive" role="alert">
                                <p v-for="error in recipeItemErrors(index)" :key="`${index}-${error}`">{{ error }}</p>
                            </div>
                        </div>
                        <Button type="button" variant="ghost" size="icon" aria-label="Remove ingredient" @click="removeItem(index)">
                            <Trash2 :size="16" />
                        </Button>
                    </div>
                </div>
                <span v-if="recipeForm.errors.items" class="block text-sm text-destructive">{{ recipeForm.errors.items }}</span>

                <label class="block">
                    <span class="field-label">Add food</span>
                    <div class="relative mt-1">
                        <Search :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="searchQuery" class="pl-9" placeholder="Search foods" @input="queueFoodSearch" />
                    </div>
                </label>
                <div v-if="searchResults.length" class="grid gap-2">
                    <Button
                        v-for="product in searchResults"
                        :key="product.id"
                        type="button"
                        variant="surface"
                        class="h-auto w-full min-w-0 justify-start overflow-hidden p-3 text-left"
                        @click="addProduct(product)"
                    >
                        <span class="min-w-0 flex-1 overflow-hidden">
                            <span class="block truncate font-semibold">{{ product.name }}</span>
                            <span class="block truncate text-sm text-muted-foreground">{{ product.calories_per_100 }} kcal / 100{{ product.nutrition_unit || 'g' }}</span>
                        </span>
                    </Button>
                </div>
                <p v-else-if="searchLoading" class="text-sm text-muted-foreground">Searching…</p>
                <div v-else-if="searchError" class="rounded-xl bg-danger-soft p-3 text-sm text-danger-soft-foreground" role="alert">
                    <p>{{ searchError }}</p>
                    <Button type="button" variant="ghost" class="mt-2" @click="retryFoodSearch">Retry</Button>
                </div>
                <p v-else-if="searchQuery.trim().length === 1" class="text-sm text-muted-foreground">Enter one more character to search.</p>

                <div class="rounded-xl bg-muted/60 p-3">
                    <p class="field-label">Custom ingredient</p>
                    <div class="mt-2 grid gap-2">
                        <Input v-model="customItem.name" placeholder="Name" />
                        <div class="grid grid-cols-[minmax(0,1fr)_4.5rem] gap-2">
                            <Input v-model.number="customItem.portion_quantity" type="number" min="0.1" step="0.1" />
                            <Select v-model="customItem.portion_unit">
                                <SelectTrigger class="px-2"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="g">g</SelectItem>
                                    <SelectItem value="ml">ml</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <Input v-model.number="customItem.protein_g" type="number" min="0" step="0.1" placeholder="P" />
                            <Input v-model.number="customItem.carbs_g" type="number" min="0" step="0.1" placeholder="C" />
                            <Input v-model.number="customItem.fat_g" type="number" min="0" step="0.1" placeholder="F" />
                        </div>
                        <p v-if="customItemError" class="text-sm text-destructive" role="alert">{{ customItemError }}</p>
                        <Button type="button" variant="surface" :disabled="!customItem.name.trim()" @click="addCustomItem">
                            <Plus :size="16" />
                            Add ingredient
                        </Button>
                    </div>
                </div>

                <p class="text-sm text-muted-foreground">{{ recipeTotals.calories }} kcal · P {{ recipeTotals.protein_g }}g · C {{ recipeTotals.carbs_g }}g · F {{ recipeTotals.fat_g }}g</p>
                <div class="grid grid-cols-2 gap-2">
                    <Button type="button" variant="surface" @click="returnToRecipes">Cancel</Button>
                    <Button :disabled="recipeForm.items.length === 0" :loading="recipeForm.processing" :loading-label="editingRecipe ? 'Updating recipe…' : 'Saving recipe…'">
                        {{ editingRecipe ? 'Update recipe' : 'Save recipe' }}
                    </Button>
                </div>
            </form>
        </Card>

        <Card v-else-if="selectedRecipe" key="log" data-motion-transform>
            <div class="flex items-center gap-2">
                <UtensilsCrossed :size="21" class="text-food" />
                <h2 class="card-title">{{ selectedRecipe.name }}</h2>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">{{ selectedRecipe.calories }} kcal per {{ selectedRecipe.servings }} serving{{ selectedRecipe.servings === 1 ? '' : 's' }}</p>
            <form class="mt-4 space-y-3" @submit.prevent="logRecipe">
                <MealTypePicker v-model="logForm.meal_type" :meal-types="mealTypes" />
                <span v-if="logForm.errors.meal_type" class="block text-sm text-destructive" role="alert">{{ logForm.errors.meal_type }}</span>
                <label class="block">
                    <span class="field-label">Servings</span>
                    <Input v-model.number="logForm.servings" type="number" min="0.1" max="100" step="0.1" required class="mt-1"
                        @invalid.prevent="logForm.setError('servings', ($event.target as HTMLInputElement).validationMessage)"
                        @update:model-value="logForm.clearErrors('servings')"
                    />
                    <span v-if="logForm.errors.servings" class="mt-1 block text-sm text-destructive" role="alert">{{ logForm.errors.servings }}</span>
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <Button type="button" variant="surface" @click="returnToRecipes">Back</Button>
                    <Button :loading="logForm.processing" loading-label="Logging recipe…">Log recipe</Button>
                </div>
            </form>
        </Card>

        <Card v-else key="list" data-motion-transform>
            <div class="flex items-start gap-4">
                <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-food/10 text-food">
                    <UtensilsCrossed :size="23" />
                </span>
                <div class="min-w-0">
                    <h2 class="card-title">{{ recipes.length ? 'Saved recipes' : 'Save meals you repeat' }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ recipes.length ? 'Choose a recipe to log it.' : 'Group ingredients once, then log the whole meal in a tap.' }}
                    </p>
                </div>
            </div>

            <Button class="mt-5 w-full" @click="startCreate">
                <Plus :size="19" />
                {{ recipes.length ? 'New recipe' : 'Create your first recipe' }}
            </Button>

            <div v-if="recipes.length" class="mt-4 divide-y divide-border/60">
                <div v-for="recipe in recipes" :key="recipe.id" class="flex items-center gap-1">
                    <Button type="button" variant="ghost" class="h-auto min-w-0 flex-1 justify-start overflow-hidden rounded-xl px-3 py-3 text-left" @click="startLog(recipe)">
                        <span class="min-w-0 flex-1 overflow-hidden">
                            <span class="block truncate font-semibold">{{ recipe.name }}</span>
                            <span class="block truncate text-sm text-muted-foreground">{{ recipe.calories }} kcal · {{ recipe.items.length }} ingredient{{ recipe.items.length === 1 ? '' : 's' }}</span>
                        </span>
                    </Button>
                    <Button type="button" variant="ghost" size="icon" :aria-label="`Edit ${recipe.name}`" @click="startEdit(recipe)">
                        <Pencil :size="16" />
                    </Button>
                    <Button type="button" variant="ghost" size="icon" :aria-label="`Delete ${recipe.name}`" @click="requestRecipeDelete(recipe)">
                        <Trash2 :size="16" />
                    </Button>
                </div>
            </div>
        </Card>
        </Transition>
        <ConfirmSheet
            :open="Boolean(pendingRecipeDelete)"
            title="Delete recipe"
            :message="pendingRecipeDelete ? `Delete ${pendingRecipeDelete.name}?` : ''"
            :processing="logForm.processing"
            processing-label="Deleting recipe…"
            :error="recipeDeleteError"
            @cancel="cancelRecipeDelete"
            @confirm="confirmRecipeDelete"
        />
    </div>
</template>
