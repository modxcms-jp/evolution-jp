<?php

/**
 * マイグレーションの記録の表（migrations）の文字セットと照合順序を system_settings にそろえる。
 * 以前の版は照合順序を指定せずに作っていたので、DB の既定（MySQL 8 では utf8mb4_0900_ai_ci）になっていた。
 */
return static function (EvoMigrationHelper $m) {
    $m->matchTableCollation('migrations', 'system_settings');
};
