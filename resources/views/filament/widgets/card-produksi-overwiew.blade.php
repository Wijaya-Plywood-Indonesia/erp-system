<x-filament-widgets::widget>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        
        <x-overview-produksi-card
            title="Total Batang" 
            :value="$total_kayu" 
            icon="heroicon-m-circle-stack" 
            color="blue" 
        />

        <x-overview-produksi-card
            title="Grade Premium" 
            :value="$grade_a" 
            icon="heroicon-m-star" 
            color="green" 
        />

        <x-overview-produksi-card
            title="Grade Standar" 
            :value="$grade_c ?? 0" 
            icon="heroicon-m-exclamation-triangle" 
            color="orange" 
        />

    </div>
</x-filament-widgets::widget>