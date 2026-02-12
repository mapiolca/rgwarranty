-- RGW cycle
CREATE TABLE llx_rgw_cycle (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer NOT NULL,
	ref varchar(32) NOT NULL,
	situation_cycle_ref integer NULL,
	fk_projet integer NULL,
	fk_soc integer NULL,
	date_reception date NULL,
	model_pdf varchar(255) NOT NULL,
	date_limit date NULL,
	status smallint NOT NULL DEFAULT 0,
	note_private text NULL,
	fk_user_author integer NULL,
	fk_user_modif integer NULL,
	datec datetime NULL,
	tms timestamp
) ENGINE=innodb;

CREATE INDEX idx_rgw_cycle_entity ON llx_rgw_cycle (entity);
CREATE INDEX idx_rgw_cycle_entity_soc ON llx_rgw_cycle (entity, fk_soc);
CREATE INDEX idx_rgw_cycle_entity_project ON llx_rgw_cycle (entity, fk_projet);
CREATE INDEX idx_rgw_cycle_entity_situation ON llx_rgw_cycle (entity, situation_cycle_ref);
