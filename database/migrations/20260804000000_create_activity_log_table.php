<?php

/**
 * @property mixed $load
 * @property CI_DB_forge $dbforge
 */
class Migration_create_activity_log_table extends CI_Migration
{
    protected string $table = 'activity_log';

    public function __construct()
    {
        parent::__construct();

        $this->load->dbforge();
    }

    public function up(): void
    {
        if ($this->db->table_exists($this->table)) {
            return;
        }

        $this->dbforge->id();
        $this->dbforge->string('log_name')->nullable()->index();
        $this->dbforge->text('description');
        $this->dbforge->string('subject_type')->nullable()->index();
        $this->dbforge->unsignedBigInteger('subject_id')->nullable()->index();
        $this->dbforge->string('event')->nullable()->index();
        $this->dbforge->string('causer_type')->nullable()->index();
        $this->dbforge->unsignedBigInteger('causer_id')->nullable()->index();
        $this->dbforge->json('attribute_changes')->nullable();
        $this->dbforge->json('properties')->nullable();
        $this->dbforge->timestamps();
        $this->dbforge->create_table($this->table);
    }

    public function down(): void
    {
        $this->dbforge->drop_table($this->table);
    }
}


