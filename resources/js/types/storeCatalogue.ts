export interface StoreAttributeValue {
    id: number;
    attribute_id: number;
    value: string;
}

export interface StoreAttribute {
    id: number;
    name: string;
    values?: StoreAttributeValue[];
}

export interface StoreColor {
    id: number;
    name: string;
    code: string;
}
