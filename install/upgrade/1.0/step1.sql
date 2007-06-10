CREATE TABLE item_instance_relationship (
    sequence_number INT( 10 ) NOT NULL AUTO_INCREMENT,
    item_id INT( 10 ) NOT NULL,
    instance_no SMALLINT( 5 ) NOT NULL,
    related_item_id INT( 10 ) NOT NULL,
    related_instance_no SMALLINT( 5 ) NOT NULL,
PRIMARY KEY ( sequence_number ),
UNIQUE KEY ( item_id, instance_no, related_item_id, related_instance_no )
) TYPE=MyISAM COMMENT = 'item instance relationship table';

# delete configuration entries for linked item support.

DELETE FROM s_config_group_item WHERE group_id = 'item_input' AND id IN('linked_item_support', 'link_same_type_only', 'confirm_duplicate_linked_item_insert', 'confirm_linked_item_delete');
DELETE FROM s_config_group_item WHERE group_id = 'listings' AND id IN('linked_items');

DELETE FROM s_config_group_item_var WHERE group_id = 'item_input' AND id IN('linked_item_support', 'link_same_type_only', 'confirm_duplicate_linked_item_insert', 'confirm_linked_item_delete');
DELETE FROM s_config_group_item_var WHERE group_id = 'listings' AND id IN('linked_items');

# delete linked item vars, replace with related item vars

DELETE FROM s_language_var WHERE language = 'ENGLISH' AND varname IN('related_item(s)');

INSERT INTO s_language_var (language, varname, value) VALUES ('ENGLISH', 'related_item(s)', 'Related Item(s)'); 
