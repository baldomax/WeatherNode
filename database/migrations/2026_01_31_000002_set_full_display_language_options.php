<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Set display.language options to the full list of supported locales
     * (auto + all entries from config/localization.php). Does not change
     * the current value.
     */
    public function up(): void
    {
        $options = 'auto:Auto (browser),nl-nl:Nederlands,en-us:English (US),en-gb:English (UK),de-de:Deutsch,fr-fr:Français,es-es:Español,it-it:Italiano,pt-pt:Português (PT),pt-br:Português (BR),pl-pl:Polski,da-dk:Dansk,nn-no:Norsk,sv-se:Svenska,fi-fi:Suomi,el-gr:Ελληνικά,hr-hr:Hrvatski,sr-rs:Srpski,ca-es:Català';

        DB::table('settings')
            ->where('key', 'display.language')
            ->update(['options' => $options, 'updated_at' => now()]);
    }

    /**
     * Reverse: we cannot restore previous options. No-op.
     */
    public function down(): void
    {
        // Options are additive; no safe way to revert to prior list.
    }
};
