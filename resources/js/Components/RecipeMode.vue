<script setup lang="ts">
import axios from 'axios';
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Plus, Search, Trash2, UtensilsCrossed } from '@lucide/vue';
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
const selectedRecipe = ref<Recipe | null>(null);
const pendingRecipeDelete = ref<Recipe | null>(null);
const searchQuery = ref('');
const searchResults = ref<FoodProduct[]>([]);
const searchLoading = ref(false);
let searchRequest = 0;

const recipeForm = useForm({
    date: props.date,
    name: '',
    servings: 1,
    items: [] as RecipeItem[],
});

const logForm = useForm({
    date: props.date,
    meal_type: props.meal || 'breakfast',
    recipe_id: '',
    servings: 1,
});

const customItem = ref({
    name: '',
    portion_quantity: 100,
    portion_unit: 'g' as 'g' | 'ml',
    protein_g: 0,
    carbs_g: 0,
    fat_g: 0,
});

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

function startCreate(): void {
    creating.value = true;
    selectedRecipe.value = null;
    recipeForm.name = '';
    recipeForm.servings = 1;
    recipeForm.items = [];
}

function startLog(recipe: Recipe): void {
    selectedRecipe.value = recipe;
    creating.value = false;
    logForm.recipe_id = recipe.id;
    logForm.servings = recipe.servings;
    logForm.meal_type = props.meal || 'breakfast';
}

function addCustomItem(): void {
    if (!customItem.value.name) {
        return;
    }

    recipeForm.items.push({
        name: customItem.value.name,
        food_product_id: null,
        portion_quantity: Number(customItem.value.portion_quantity),
        portion_unit: customItem.value.portion_unit,
        calories: customCalories(),
        protein_g: Number(customItem.value.protein_g),
        carbs_g: Number(customItem.value.carbs_g),
        fat_g: Number(customItem.value.fat_g),
    });
    customItem.value = { name: '', portion_quantity: 100, portion_unit: 'g', protein_g: 0, carbs_g: 0, fat_g: 0 };
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
    searchQuery.value = '';
    searchResults.value = [];
}

function removeItem(index: number): void {
    recipeForm.items.splice(index, 1);
}

async function searchFoods(): Promise<void> {
    const query = searchQuery.value.trim();
    const request = ++searchRequest;

    if (query.length < 2) {
        searchResults.value = [];
        searchLoading.value = false;
        return;
    }

    searchLoading.value = true;

    try {
        const { data } = await axios.get('/food-products/search', { params: { q: query } });

        if (request === searchRequest) {
            searchResults.value = (data.products || []).filter((result: { type?: string }) => result.type !== 'previous_meal');
        }
    } catch {
        if (request === searchRequest) {
            searchResults.value = [];
        }
    } finally {
        if (request === searchRequest) {
            searchLoading.value = false;
        }
    }
}

function saveRecipe(): void {
    recipeForm.post('/recipes', {
        onSuccess: () => {
            recipeForm.reset();
            creating.value = false;
        },
    });
}

function logRecipe(): void {
    logForm.post('/meals/recipe');
}

function requestRecipeDelete(recipe: Recipe): void { pendingRecipeDelete.value = recipe; }
function cancelRecipeDelete(): void { pendingRecipeDelete.value = null; }
function confirmRecipeDelete(): void {
    const recipe = pendingRecipeDelete.value;

    if (!recipe) {
        return;
    }

    pendingRecipeDelete.value = null;
    logForm.delete(`/recipes/${recipe.id}`, { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-4">
        <Card v-if="creating">
            <div class="flex items-center gap-2">
                <UtensilsCrossed :size="21" class="text-food" />
                <h2 class="card-title">New recipe</h2>
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
                        <Input v-model="searchQuery" class="pl-9" placeholder="Search foods" @input="searchFoods" />
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
                        <Button type="button" variant="surface" :disabled="!customItem.name" @click="addCustomItem">
                            <Plus :size="16" />
                            Add ingredient
                        </Button>
                    </div>
                </div>

                <p class="text-sm text-muted-foreground">{{ recipeTotals.calories }} kcal · P {{ recipeTotals.protein_g }}g · C {{ recipeTotals.carbs_g }}g · F {{ recipeTotals.fat_g }}g</p>
                <div class="grid grid-cols-2 gap-2">
                    <Button type="button" variant="surface" @click="creating = false">Cancel</Button>
                    <Button :disabled="recipeForm.processing || recipeForm.items.length === 0">Save recipe</Button>
                </div>
            </form>
        </Card>

        <Card v-else-if="selectedRecipe">
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
                    <Input v-model.number="logForm.servings" type="number" min="0.1" step="0.1" class="mt-1" />
                    <span v-if="logForm.errors.servings" class="mt-1 block text-sm text-destructive" role="alert">{{ logForm.errors.servings }}</span>
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <Button type="button" variant="surface" @click="selectedRecipe = null">Back</Button>
                    <Button :disabled="logForm.processing">Log recipe</Button>
                </div>
            </form>
        </Card>

        <Card v-else>
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
                            <span class="block truncate text-sm text-muted-foreground">{{ recipe.calories }} kcal · {{ recipe.items.length }} ingredients</span>
                        </span>
                    </Button>
                    <Button type="button" variant="ghost" size="icon" aria-label="Delete recipe" @click="requestRecipeDelete(recipe)">
                        <Trash2 :size="16" />
                    </Button>
                </div>
            </div>
        </Card>
        <ConfirmSheet
            :open="Boolean(pendingRecipeDelete)"
            title="Delete recipe"
            :message="pendingRecipeDelete ? `Delete ${pendingRecipeDelete.name}?` : ''"
            @cancel="cancelRecipeDelete"
            @confirm="confirmRecipeDelete"
        />
    </div>
</template>
