<?php

const A = 0,
    B = 1.0,
    C = 'A',
    D = E;

#[Example]
const WithOneAttribute = 1;

#[First]
#[Second]
const WithUngroupedAttriutes = 2;

#[First, Second]
const WithGroupAttributes = 3;

#[Example]
const ThisIsInvalid = 4,
    AttributesOnMultipleConstants = 5;
