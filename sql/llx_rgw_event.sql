-- RGW events
CREATE TABLE llx_rgw_event (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer NOT NULL,
	fk_cycle integer NOT NULL,
	date_event datetime NOT NULL,
	event_type varchar(32) NOT NULL,
	label varchar(255) NULL,
	fk_user integer NULL,
	fk_actioncomm integer NULL,
	fk_paiement integer NULL,
	fk_bank integer NULL,
	extraparams text NULL,
	datec datetime NULL,
	tms timestamp
) ENGINE=innodb;

CREATE INDEX idx_rgw_event_cycle ON llx_rgw_event (fk_cycle);
CREATE INDEX idx_rgw_event_entity ON llx_rgw_event (entity);
