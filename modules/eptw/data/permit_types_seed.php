<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ePTW — the 17 permit templates, digitised from the V3 (GCC Standard) forms.
 *
 * Each template carries: the hazard list (Yes/No), the control-measure
 * sections (Yes/No/N.A. + remark), the type-specific header fields, the
 * personnel fields, the approval steps and the keywords the hazard engine
 * uses. Everything is editable afterwards from Setup → Permit types; this
 * file is only the first fill.
 */
function eptw_permit_types_seed()
{
    $yn_header = [
        ['key' => 'h2s_zone',  'label' => 'H2S Zone',  'type' => 'yesno'],
        ['key' => 'hac_zone',  'label' => 'HAC Zone',  'type' => 'yesno'],
        ['key' => 'dust_zone', 'label' => 'Dust Zone', 'type' => 'yesno'],
    ];

    $people = function (array $labels) {
        $out = [];
        foreach ($labels as $label) {
            $out[] = ['key' => 'p_' . strtolower(preg_replace('/[^a-z0-9]+/i', '_', $label)), 'label' => $label, 'type' => 'text', 'group' => 'personnel'];
        }

        return $out;
    };

    $full_approvals  = ['area_authority', 'hse', 'coordinator'];
    $light_approvals = ['area_authority', 'hse', 'coordinator'];

    return [
        [
            'code' => 'HW', 'name' => 'Hot Work Permit', 'icon' => 'fa-solid fa-fire', 'color' => '#dc2626',
            'description' => 'Welding, cutting, grinding, brazing, heating and any spark- or flame-producing work.',
            'high_risk' => 1, 'gas_test_required' => 1, 'isolation_required' => 1, 'default_validity_hours' => 12,
            'keywords' => ['weld', 'cut', 'grind', 'braz', 'solder', 'torch', 'blast', 'hot tap'],
            'extra_fields' => array_merge($yn_header, [
                ['key' => 'classification', 'label' => 'Hot work classification', 'type' => 'checkboxes', 'options' => ['Welding', 'Cutting', 'Grinding', 'Brazing/Soldering', 'Heating', 'Engine/Hot Surface', 'Thermal Work', 'Grit Blasting', 'Other']],
            ]),
            'hazards' => ['Flammable Gas', 'Toxic Gas', 'Oxygen Deficiency', 'Fire/Explosion', 'Heat Stress', 'Fumes/Smoke', 'Stored Energy', 'Confined Area', 'Electrical Hazard', 'Fall Hazard', 'Pressurized Systems', 'Environmental Risk'],
            'controls' => [
                ['title' => 'Control measures (mandatory)', 'items' => ['Fire Watch Assigned', 'Fire Extinguishers (Type/Qty)', 'Fire Blanket Available', 'Combustibles Removed (10m radius)', 'Drains Covered', 'Sparks Contained', 'Welding Screens Installed', 'Equipment Inspected & Certified', 'Area Barricaded & Signage', 'Permit Displayed at Worksite', 'Emergency Access Clear', 'Wind/Weather Acceptable', 'Gas Testing Completed', 'Continuous Monitoring Required', 'Communication System Available', 'Standby Personnel Available', 'Lighting Adequate', 'Housekeeping Adequate', 'PPE Verified', 'Emergency Response Ready']],
            ],
            'ppe' => ['Hard hat', 'Safety glasses', 'FR coveralls', 'Leather gloves', 'Welding shield / face shield', 'Safety boots'],
            'approvals' => $full_approvals,
        ],
        [
            'code' => 'CW', 'name' => 'Cold Work Permit', 'icon' => 'fa-solid fa-screwdriver-wrench', 'color' => '#0284c7',
            'description' => 'Mechanical, civil, painting, cleaning and other non-spark work.',
            'high_risk' => 0, 'gas_test_required' => 0, 'isolation_required' => 0, 'default_validity_hours' => 12,
            'keywords' => ['mechanical', 'civil', 'paint', 'clean', 'inspect', 'assembl', 'maintenance'],
            'extra_fields' => array_merge($yn_header, [
                ['key' => 'work_type', 'label' => 'Cold work type', 'type' => 'checkboxes', 'options' => ['Mechanical', 'Civil', 'Painting', 'Cleaning', 'Inspection', 'Non-spark tools', 'Assembly/Disassembly', 'Maintenance', 'Other']],
                ['key' => 'special_conditions', 'label' => 'Special conditions', 'type' => 'checkboxes', 'options' => ['Work at Height', 'Confined Space', 'Electrical', 'Excavation', 'Lifting', 'Chemical Work', 'Traffic Control', 'Environmental Risk']],
            ]),
            'hazards' => ['Slips/Trips/Falls', 'Manual Handling', 'Dust Exposure', 'Chemical Exposure', 'Noise', 'Ergonomic Risk', 'Moving Machinery', 'Confined Area', 'Electrical Hazard', 'Falling Objects', 'Environmental Impact', 'Traffic Risk'],
            'controls' => [
                ['title' => 'Control measures (mandatory)', 'items' => ['Housekeeping Adequate', 'PPE Available & Used', 'Tools Inspected & Safe', 'Area Barricaded & Signage', 'Access/Egress Clear', 'Lighting Adequate', 'Dust Control Measures', 'Noise Control Measures', 'Manual Handling Aids Used', 'Equipment Certified', 'Scaffolding/Platforms Safe', 'Fall Protection (if required)', 'Electrical Safety Measures', 'Chemical Handling Controls', 'Waste Management Implemented', 'Spill Prevention Measures', 'Traffic Management in Place', 'Emergency Access Clear', 'Communication Available', 'Supervision Adequate']],
            ],
            'ppe' => ['Hard hat', 'Safety glasses', 'Coveralls', 'Gloves', 'Safety boots', 'Hi-vis vest'],
            'approvals' => $light_approvals,
        ],
        [
            'code' => 'CSE', 'name' => 'Confined Space Entry Permit', 'icon' => 'fa-solid fa-person-through-window', 'color' => '#7c3aed',
            'description' => 'Entry into tanks, vessels, pits, manholes, ducts and any space with restricted access.',
            'high_risk' => 1, 'gas_test_required' => 1, 'isolation_required' => 1, 'default_validity_hours' => 8,
            'keywords' => ['tank', 'vessel', 'manhole', 'pit', 'silo', 'confined', 'entry', 'duct'],
            'extra_fields' => array_merge([
                ['key' => 'space_id',   'label' => 'Confined Space ID', 'type' => 'text'],
                ['key' => 'space_type', 'label' => 'Confined space type', 'type' => 'text'],
            ], $people(['Entrants', 'Standby Man', 'Supervisor', 'Rescue Team', 'Gas Tester'])),
            'hazards' => ['Oxygen Deficiency', 'Toxic Gas', 'Flammable Gas', 'Engulfment', 'Restricted Access', 'Heat Stress', 'Biological Hazard'],
            'controls' => [
                ['title' => 'CSE control measures', 'items' => ['Entry Authorized', 'Isolation Completed', 'LOTO Applied', 'Ventilation Provided', 'Gas Testing Done', 'Continuous Monitoring', 'Rescue Plan Ready', 'Communication Available', 'PPE Available', 'Lighting Safe', 'Permit Displayed', 'Emergency Plan Ready']],
            ],
            'ppe' => ['Hard hat', 'Gas detector', 'Full body harness with retrieval line', 'Escape set / SCBA', 'Intrinsically safe torch'],
            'approvals' => $full_approvals,
        ],
        [
            'code' => 'EX', 'name' => 'Excavation Permit', 'icon' => 'fa-solid fa-person-digging', 'color' => '#a16207',
            'description' => 'Any breaking of ground, trenching or excavation.',
            'high_risk' => 1, 'gas_test_required' => 0, 'isolation_required' => 0, 'default_validity_hours' => 24,
            'keywords' => ['excavat', 'trench', 'dig', 'backfill', 'underground'],
            'extra_fields' => [
                ['key' => 'depth',  'label' => 'Excavation depth (m)', 'type' => 'number'],
                ['key' => 'length', 'label' => 'Length (m)', 'type' => 'number'],
                ['key' => 'width',  'label' => 'Width (m)', 'type' => 'number'],
                ['key' => 'services', 'label' => 'Underground services check', 'type' => 'detect', 'options' => ['Electrical Cable', 'Water Line', 'Sewer Line', 'Gas Line', 'Telecom', 'Drainage']],
            ],
            'hazards' => ['Trench collapse', 'Underground services', 'Falling Objects', 'Water ingress / flooding', 'Moving Machinery', 'Traffic Risk', 'Toxic Gas'],
            'controls' => [
                ['title' => 'Safety controls', 'items' => ['Barricading Provided', 'Warning Signage', 'Permit Displayed', 'Access Ladder Provided', 'Shoring/Sloping Provided', 'Daily Inspection', 'Spoil at Safe Distance', 'Equipment Safe', 'Spotter Available', 'Traffic Control', 'Emergency Plan', 'Lighting Adequate']],
            ],
            'ppe' => ['Hard hat', 'Hi-vis vest', 'Safety boots', 'Gloves'],
            'approvals' => $full_approvals,
        ],
        [
            'code' => 'LF', 'name' => 'Lifting Permit', 'icon' => 'fa-solid fa-truck-pickup', 'color' => '#ea580c',
            'description' => 'Crane and mechanical lifting operations, including critical and tandem lifts.',
            'high_risk' => 1, 'gas_test_required' => 0, 'isolation_required' => 0, 'default_validity_hours' => 12,
            'keywords' => ['crane', 'lift', 'hoist', 'rigging', 'sling', 'load'],
            'extra_fields' => array_merge([
                ['key' => 'lift_type',   'label' => 'Lift type', 'type' => 'select', 'options' => ['Routine', 'Non-routine', 'Critical', 'Tandem']],
                ['key' => 'crane_type',  'label' => 'Crane type', 'type' => 'text'],
                ['key' => 'load_desc',   'label' => 'Load description', 'type' => 'text'],
                ['key' => 'weight_kg',   'label' => 'Weight (kg)', 'type' => 'number'],
                ['key' => 'radius_m',    'label' => 'Radius (m)', 'type' => 'number'],
                ['key' => 'height_m',    'label' => 'Height (m)', 'type' => 'number'],
                ['key' => 'crane_count', 'label' => 'No. of cranes', 'type' => 'number'],
                ['key' => 'tandem',      'label' => 'Tandem lift', 'type' => 'yesno'],
                ['key' => 'critical',    'label' => 'Critical lift', 'type' => 'yesno'],
            ], $people(['Lift Supervisor', 'Crane Operator', 'Rigger', 'Signalman', 'Banksman'])),
            'hazards' => ['Dropped load', 'Crane overturn', 'Crushing', 'Falling Objects', 'Overhead power lines', 'High wind', 'Ground failure'],
            'controls' => [
                ['title' => 'Equipment check', 'items' => ['Crane Certification Valid', 'Rigging Gear Certified', 'Sling Condition OK', 'Hooks with Safety Latch', 'Load Chart Available', 'Ground Condition Stable', 'Outriggers Deployed', 'Wind Speed Checked', 'No Overloading', 'Communication System OK']],
                ['title' => 'Control measures', 'items' => ['Barricading Area', 'Taglines Used', 'Clear Communication', 'No Unauthorized Access', 'Weather Safe', 'Lift Plan Approved', 'Trial Lift Conducted', 'Emergency Plan Ready']],
            ],
            'ppe' => ['Hard hat', 'Hi-vis vest', 'Gloves', 'Safety boots'],
            'approvals' => $full_approvals,
        ],
        [
            'code' => 'WAH', 'name' => 'Working at Height Permit', 'icon' => 'fa-solid fa-stairs', 'color' => '#0891b2',
            'description' => 'Work at 1.8 m or more, on scaffolds, roofs, ladders, MEWPs and open edges.',
            'high_risk' => 1, 'gas_test_required' => 0, 'isolation_required' => 0, 'default_validity_hours' => 12,
            'keywords' => ['height', 'scaffold', 'roof', 'ladder', 'edge', 'elevat'],
            'extra_fields' => array_merge([
                ['key' => 'height_m',  'label' => 'Working height (m)', 'type' => 'number'],
                ['key' => 'work_type', 'label' => 'Work type', 'type' => 'select', 'options' => ['Scaffold', 'Ladder', 'Roof', 'MEWP', 'Rope access', 'Open edge', 'Other']],
            ], $people(['Supervisor', 'Workers', 'Rescue Person'])),
            'hazards' => ['Fall Hazard', 'Falling Objects', 'Fragile surface', 'High wind', 'Electrical Hazard', 'Suspension trauma'],
            'controls' => [
                ['title' => 'Safety controls', 'items' => ['Full Body Harness Used', 'Double Lanyard', 'Anchorage Point Verified', 'Scaffolding Inspected', 'Guardrails Installed', 'Toe Boards Provided', 'Fall Arrest System', 'Rescue Plan Available', 'Tool Lanyards Used', 'Weather Condition Safe', 'Access Ladder Safe', 'Permit Displayed']],
                ['title' => 'Equipment', 'items' => ['Scaffold Tag Valid', 'Harness Certified', 'Lanyard Condition Good', 'Anchorage Strength Verified', 'MEWP Inspected', 'Tool Condition Safe']],
            ],
            'ppe' => ['Full body harness', 'Double lanyard', 'Hard hat with chin strap', 'Safety boots'],
            'approvals' => $full_approvals,
        ],
        [
            'code' => 'EL', 'name' => 'Electrical Work Permit', 'icon' => 'fa-solid fa-bolt', 'color' => '#ca8a04',
            'description' => 'Work on or near electrical equipment, panels, cables and switchgear.',
            'high_risk' => 1, 'gas_test_required' => 0, 'isolation_required' => 1, 'default_validity_hours' => 12,
            'keywords' => ['electric', 'cable', 'panel', 'switchgear', 'voltage', 'transformer', 'mcc'],
            'extra_fields' => array_merge([
                ['key' => 'voltage',   'label' => 'Voltage level', 'type' => 'select', 'options' => ['ELV (<50V)', 'LV (<1kV)', 'MV (1–33kV)', 'HV (>33kV)']],
                ['key' => 'equipment', 'label' => 'Equipment', 'type' => 'text'],
            ], $people(['Electrical Supervisor', 'Technician', 'Authorized Person'])),
            'hazards' => ['Electrical Hazard', 'Arc flash', 'Stored Energy', 'Fire/Explosion', 'Fall Hazard', 'Confined Area'],
            'controls' => [
                ['title' => 'Isolation check', 'items' => ['Power Isolated', 'LOTO Applied', 'Zero Energy Verified', 'Test Before Touch', 'Permit Displayed', 'Warning Signs Installed']],
                ['title' => 'Control measures', 'items' => ['Insulated Tools Used', 'Rubber Gloves Used', 'Arc Flash Protection', 'Safe Distance Maintained', 'Barricading Area', 'Emergency Plan Ready', 'Fire Extinguisher Available', 'No Live Work (unless approved)']],
            ],
            'ppe' => ['Insulated gloves', 'Arc-rated clothing', 'Face shield', 'Dielectric boots'],
            'approvals' => $full_approvals,
        ],
        [
            'code' => 'LOTO', 'name' => 'LOTO / Isolation Permit', 'icon' => 'fa-solid fa-lock', 'color' => '#4f46e5',
            'description' => 'Lock-out / tag-out and isolation of electrical, mechanical, hydraulic, pneumatic, thermal and chemical energy.',
            'high_risk' => 1, 'gas_test_required' => 0, 'isolation_required' => 1, 'default_validity_hours' => 24,
            'keywords' => ['loto', 'lock', 'tag', 'isolat', 'de-energ', 'blind'],
            'extra_fields' => [
                ['key' => 'equipment',       'label' => 'Equipment', 'type' => 'text'],
                ['key' => 'isolation_type',  'label' => 'Isolation type', 'type' => 'select', 'options' => ['Electrical', 'Mechanical', 'Process', 'Instrument', 'Multiple']],
                ['key' => 'energy_sources',  'label' => 'Energy sources', 'type' => 'checkboxes', 'options' => ['Electrical', 'Mechanical', 'Hydraulic', 'Pneumatic', 'Thermal', 'Chemical', 'Stored Energy']],
                ['key' => 'isolation_steps', 'label' => 'Isolation steps (one per line: step — responsible)', 'type' => 'textarea'],
                ['key' => 'lock_register',   'label' => 'Lock / tag register (lock no, tag no, applied by)', 'type' => 'textarea'],
            ],
            'hazards' => ['Stored Energy', 'Electrical Hazard', 'Unexpected start-up', 'Pressurized Systems', 'Chemical Exposure', 'Thermal burn'],
            'controls' => [
                ['title' => 'Verification', 'items' => ['Isolation Completed', 'Zero Energy Verified', 'Test Before Touch', 'All Locks Applied', 'Area Safe', 'Permit Displayed']],
            ],
            'ppe' => ['Hard hat', 'Safety glasses', 'Gloves', 'Safety boots'],
            'approvals' => $full_approvals,
        ],
        [
            'code' => 'RT', 'name' => 'Radiography Permit', 'icon' => 'fa-solid fa-radiation', 'color' => '#be123c',
            'description' => 'Industrial radiography with X-ray or gamma sources.',
            'high_risk' => 1, 'gas_test_required' => 0, 'isolation_required' => 0, 'default_validity_hours' => 8,
            'keywords' => ['radiograph', 'x-ray', 'gamma', 'isotope', 'ndt', 'source'],
            'extra_fields' => array_merge([
                ['key' => 'rt_type',     'label' => 'Radiography type', 'type' => 'select', 'options' => ['X-ray', 'Gamma']],
                ['key' => 'source_type', 'label' => 'Source type / activity', 'type' => 'text'],
                ['key' => 'monitoring',  'label' => 'Radiation monitoring log (time, level, distance, measured by)', 'type' => 'textarea'],
            ], $people(['Radiography Supervisor', 'Radiographer', 'Assistant', 'Radiation Safety Officer (RSO)'])),
            'hazards' => ['Ionising radiation', 'Unauthorised entry', 'Source loss', 'Night / poor visibility'],
            'controls' => [
                ['title' => 'Control measures', 'items' => ['Controlled Area Established', 'Barricading & Signage Installed', 'Warning Lights Provided', 'Access Restricted', 'Dose Meter Provided', 'Radiation Survey Meter Available', 'Emergency Plan Available', 'Communication Available', 'Permit Displayed', 'Authorized Personnel Only']],
            ],
            'ppe' => ['Dosimeter', 'Survey meter', 'Hi-vis vest'],
            'approvals' => $full_approvals,
        ],
        [
            'code' => 'MEWP', 'name' => 'Manlift / MEWP Permit', 'icon' => 'fa-solid fa-arrow-up-from-ground-water', 'color' => '#0d9488',
            'description' => 'Boom lifts, cherry pickers and other mobile elevating work platforms.',
            'high_risk' => 0, 'gas_test_required' => 0, 'isolation_required' => 0, 'default_validity_hours' => 12,
            'keywords' => ['manlift', 'mewp', 'boom', 'cherry picker', 'aerial'],
            'extra_fields' => array_merge([
                ['key' => 'mewp_type',    'label' => 'MEWP type', 'type' => 'text'],
                ['key' => 'equipment_id', 'label' => 'Equipment ID', 'type' => 'text'],
                ['key' => 'max_height',   'label' => 'Max height (m)', 'type' => 'number'],
                ['key' => 'capacity_kg',  'label' => 'Capacity (kg)', 'type' => 'number'],
            ], $people(['Operator', 'Supervisor', 'Banksman/Spotter'])),
            'hazards' => ['Fall Hazard', 'Overturn', 'Crushing / entrapment', 'Overhead power lines', 'High wind', 'Falling Objects'],
            'controls' => [
                ['title' => 'Pre-use inspection', 'items' => ['MEWP Certification Valid', 'Operator License Valid', 'Daily Inspection Completed', 'Emergency Controls Working', 'Guardrails Intact', 'Platform Gate Secure', 'Tires/Tracks Condition Good', 'Hydraulic System No Leak', 'Battery/Fuel Level Adequate', 'Alarm/Horn Working', 'Tilt Sensor Functional', 'Wind Speed Within Limits']],
                ['title' => 'Control measures', 'items' => ['Full Body Harness Used', 'Lanyard Attached to Anchor', 'Area Barricaded', 'No Overloading', 'Ground Condition Stable', 'No Unauthorized Access', 'Weather Safe', 'No Overhead Obstructions', 'Communication Available', 'Emergency Rescue Plan Available', 'Permit Displayed', 'Spotter Available']],
            ],
            'ppe' => ['Full body harness', 'Hard hat with chin strap', 'Hi-vis vest'],
            'approvals' => $light_approvals,
        ],
        [
            'code' => 'SL', 'name' => 'Scissor Lift Permit', 'icon' => 'fa-solid fa-up-down', 'color' => '#059669',
            'description' => 'Scissor lift operations.',
            'high_risk' => 0, 'gas_test_required' => 0, 'isolation_required' => 0, 'default_validity_hours' => 12,
            'keywords' => ['scissor'],
            'extra_fields' => array_merge([
                ['key' => 'equipment_id', 'label' => 'Equipment ID', 'type' => 'text'],
                ['key' => 'max_height',   'label' => 'Max height (m)', 'type' => 'number'],
                ['key' => 'capacity_kg',  'label' => 'Capacity (kg)', 'type' => 'number'],
            ], $people(['Operator', 'Supervisor', 'Spotter'])),
            'hazards' => ['Fall Hazard', 'Overturn', 'Crushing / entrapment', 'Overhead obstructions', 'Falling Objects'],
            'controls' => [
                ['title' => 'Pre-use check', 'items' => ['Certification Valid', 'Operator Licensed', 'Daily Inspection Done', 'Guardrails Intact', 'Platform Gate Secure', 'Emergency Stop Working', 'Tires/Tracks OK', 'Hydraulic System OK', 'Battery/Fuel Adequate', 'Tilt Alarm Functional', 'Ground Level Safe', 'No Overhead Obstructions']],
                ['title' => 'Control measures', 'items' => ['Full Body Harness Used', 'Lanyard Attached', 'Area Barricaded', 'No Overloading', 'Ground Stable', 'No Unauthorized Access', 'Weather Safe', 'Communication Available', 'Emergency Plan Ready', 'Spotter Assigned', 'Permit Displayed', 'Safe Access Provided']],
            ],
            'ppe' => ['Full body harness', 'Hard hat', 'Hi-vis vest'],
            'approvals' => $light_approvals,
        ],
        [
            'code' => 'PL', 'name' => 'Piling Work Permit', 'icon' => 'fa-solid fa-hammer', 'color' => '#78350f',
            'description' => 'Driven, bored and CFA piling operations.',
            'high_risk' => 1, 'gas_test_required' => 0, 'isolation_required' => 0, 'default_validity_hours' => 24,
            'keywords' => ['pil', 'rig', 'auger', 'bore'],
            'extra_fields' => array_merge([
                ['key' => 'pile_type',  'label' => 'Pile type', 'type' => 'text'],
                ['key' => 'pile_depth', 'label' => 'Pile depth (m)', 'type' => 'number'],
                ['key' => 'rig_type',   'label' => 'Rig type', 'type' => 'text'],
                ['key' => 'rig_id',     'label' => 'Rig ID', 'type' => 'text'],
            ], $people(['Piling Supervisor', 'Rig Operator', 'Banksman', 'Rigger'])),
            'hazards' => ['Rig overturn', 'Noise', 'Vibration', 'Falling Objects', 'Underground services', 'Moving Machinery', 'Crushing'],
            'controls' => [
                ['title' => 'Pre-operation check', 'items' => ['Rig Certification Valid', 'Operator License Valid', 'Hydraulic System OK', 'Pile Hammer Condition Good', 'Wire Ropes Inspected', 'Emergency Stop Functional', 'Ground Stability Verified', 'Swing Radius Barricaded', 'Communication System Available', 'Lighting Adequate']],
                ['title' => 'Control measures', 'items' => ['Barricading Area', 'Exclusion Zone Established', 'Spotter/Banksman Assigned', 'PPE Used (Helmet, Gloves, Ear Protection)', 'Noise Control Measures', 'Vibration Monitoring (if required)', 'Nearby Structures Protected', 'Emergency Plan Available', 'Permit Displayed at Site']],
            ],
            'ppe' => ['Hard hat', 'Ear protection', 'Gloves', 'Hi-vis vest', 'Safety boots'],
            'approvals' => $full_approvals,
        ],
        [
            'code' => 'HT', 'name' => 'Hydrostatic Testing Permit', 'icon' => 'fa-solid fa-droplet', 'color' => '#2563eb',
            'description' => 'Hydrostatic pressure testing of pipelines and equipment.',
            'high_risk' => 1, 'gas_test_required' => 0, 'isolation_required' => 1, 'default_validity_hours' => 24,
            'keywords' => ['hydro', 'hydrostatic', 'hydrotest'],
            'extra_fields' => array_merge([
                ['key' => 'line',          'label' => 'Pipeline / equipment', 'type' => 'text'],
                ['key' => 'test_pressure', 'label' => 'Test pressure (bar)', 'type' => 'number'],
                ['key' => 'test_medium',   'label' => 'Test medium', 'type' => 'text'],
            ], $people(['Test Supervisor', 'Engineer', 'Technician', 'Inspector'])),
            'hazards' => ['Pressurized Systems', 'Line of fire', 'Stored Energy', 'Water release / flooding', 'Slips/Trips/Falls'],
            'controls' => [
                ['title' => 'Pre-test check', 'items' => ['Pressure Gauge Calibrated', 'Test Pump Condition OK', 'Hoses & Fittings Inspected', 'Isolation Valves Verified', 'Blind Flanges Installed', 'Relief Valve Installed', 'Test Area Barricaded', 'Communication System Available', 'Emergency Plan Available']],
                ['title' => 'Control measures', 'items' => ['Exclusion Zone Established', 'Pressure Release Plan Prepared', 'No Personnel in Line of Fire', 'Monitoring During Test', 'Emergency Shutdown Available', 'PPE Used (Helmet, Gloves, Eye Protection)', 'Communication Maintained', 'Permit Displayed']],
            ],
            'ppe' => ['Hard hat', 'Face shield', 'Gloves', 'Safety boots'],
            'approvals' => $full_approvals,
        ],
        [
            'code' => 'PT', 'name' => 'Pressure Testing Permit', 'icon' => 'fa-solid fa-gauge-high', 'color' => '#1d4ed8',
            'description' => 'Pneumatic and hydro pressure testing.',
            'high_risk' => 1, 'gas_test_required' => 0, 'isolation_required' => 1, 'default_validity_hours' => 24,
            'keywords' => ['pressure', 'pneumatic', 'leak test', 'psi', 'bar'],
            'extra_fields' => array_merge([
                ['key' => 'line',          'label' => 'Equipment / pipeline', 'type' => 'text'],
                ['key' => 'test_type',     'label' => 'Test type', 'type' => 'select', 'options' => ['Hydro', 'Pneumatic']],
                ['key' => 'test_pressure', 'label' => 'Test pressure (bar)', 'type' => 'number'],
                ['key' => 'test_medium',   'label' => 'Test medium', 'type' => 'text'],
            ], $people(['Test Supervisor', 'Engineer', 'Technician', 'QA/QC Inspector'])),
            'hazards' => ['Pressurized Systems', 'Line of fire', 'Stored Energy', 'Projectile / rupture', 'Noise'],
            'controls' => [
                ['title' => 'Pre-test check', 'items' => ['Pressure Gauges Calibrated', 'Test Pump/Compressor OK', 'Hoses & Fittings Inspected', 'Isolation Valves Verified', 'Relief Valve Installed', 'Test Area Barricaded', 'Communication System Available', 'Emergency Plan Available', 'Warning Signage Installed']],
                ['title' => 'Control measures', 'items' => ['Exclusion Zone Established', 'No Personnel in Line of Fire', 'Pressure Increase Controlled', 'Continuous Monitoring During Test', 'Emergency Shutdown Available', 'PPE Used (Helmet, Gloves, Eye Protection)', 'Communication Maintained', 'Permit Displayed']],
            ],
            'ppe' => ['Hard hat', 'Face shield', 'Gloves', 'Ear protection'],
            'approvals' => $full_approvals,
        ],
        [
            'code' => 'SIM', 'name' => 'SIMOPS Permit', 'icon' => 'fa-solid fa-diagram-project', 'color' => '#9333ea',
            'description' => 'Simultaneous operations — coordinated control of overlapping activities in one area.',
            'high_risk' => 1, 'gas_test_required' => 0, 'isolation_required' => 0, 'default_validity_hours' => 24,
            'keywords' => ['simops', 'simultaneous', 'concurrent', 'overlap'],
            'extra_fields' => $people(['Permit Holder', 'Supervisor', 'Engineer', 'Technician']),
            'hazards' => ['Conflicting activities', 'Communication breakdown', 'Dropped objects onto other crews', 'Fire/Explosion', 'Traffic Risk'],
            'controls' => [
                ['title' => 'Pre-work verification', 'items' => ['SIMOPS risk assessment completed', 'Coordination meeting conducted', 'Conflicting activities identified', 'Communication channels established', 'Emergency response aligned']],
                ['title' => 'Control measures', 'items' => ['Dedicated SIMOPS coordinator', 'Activity segregation implemented', 'Permit synchronization confirmed', 'Continuous monitoring', 'Immediate stop authority defined']],
            ],
            'ppe' => ['Hard hat', 'Hi-vis vest', 'Radio'],
            'approvals' => ['area_authority', 'hse', 'manager', 'coordinator'],
        ],
        [
            'code' => 'NW', 'name' => 'Night Work Permit', 'icon' => 'fa-solid fa-moon', 'color' => '#334155',
            'description' => 'Work carried out outside daylight hours.',
            'high_risk' => 0, 'gas_test_required' => 0, 'isolation_required' => 0, 'default_validity_hours' => 12,
            'keywords' => ['night', 'dark', 'after hours'],
            'extra_fields' => $people(['Permit Holder', 'Supervisor', 'Engineer', 'Technician']),
            'hazards' => ['Poor visibility', 'Fatigue', 'Security', 'Slips/Trips/Falls', 'Traffic Risk'],
            'controls' => [
                ['title' => 'Pre-work verification', 'items' => ['Area lighting adequate', 'Access routes safe', 'Emergency communication available', 'Security arrangements confirmed', 'Fatigue management considered']],
                ['title' => 'Control measures', 'items' => ['Extra lighting installed', 'Night supervisor present', 'Emergency response ready', 'Restricted access to work area', 'Permit displayed']],
            ],
            'ppe' => ['Hi-vis vest', 'Head torch', 'Hard hat'],
            'approvals' => $light_approvals,
        ],
        [
            'code' => 'HS', 'name' => 'Heat Stress / Extreme Weather Permit', 'icon' => 'fa-solid fa-temperature-high', 'color' => '#f97316',
            'description' => 'Work during extreme heat, humidity, sandstorm or other severe weather.',
            'high_risk' => 0, 'gas_test_required' => 0, 'isolation_required' => 0, 'default_validity_hours' => 12,
            'keywords' => ['heat', 'summer', 'weather', 'humid', 'storm', 'sand'],
            'extra_fields' => $people(['Permit Holder', 'Supervisor', 'Engineer', 'Technician']),
            'hazards' => ['Heat Stress', 'Dehydration', 'Sun exposure', 'Reduced visibility', 'High wind'],
            'controls' => [
                ['title' => 'Pre-work verification', 'items' => ['Weather forecast checked', 'Heat index evaluated', 'Drinking water available', 'Rest shelter available', 'First aid available']],
                ['title' => 'Control measures', 'items' => ['Work-rest schedule implemented', 'Hydration breaks enforced', 'Heat stress monitoring', 'Buddy system applied', 'PPE suitable for heat conditions']],
            ],
            'ppe' => ['Cooling vest', 'Sun protection', 'Light coveralls'],
            'approvals' => $light_approvals,
        ],
    ];
}

/**
 * Default SIMOPS conflict pairs (by type code). "block" puts the newer permit
 * on hold; "warn" only flags it.
 */
function eptw_simops_rules_seed()
{
    return [
        ['HW',  'RT',   'block', 'Hot work and radiography in the same area'],
        ['HW',  'CSE',  'block', 'Hot work near confined space entry'],
        ['HW',  'EX',   'warn',  'Hot work near excavation (services, gas pockets)'],
        ['HW',  'HT',   'warn',  'Hot work near hydrostatic testing'],
        ['HW',  'PT',   'block', 'Hot work near pressure testing'],
        ['RT',  'LF',   'block', 'Radiography near lifting operations'],
        ['RT',  'WAH',  'block', 'Radiography beneath working at height'],
        ['RT',  'CSE',  'block', 'Radiography near confined space entry'],
        ['RT',  'EX',   'warn',  'Radiography near excavation'],
        ['LF',  'WAH',  'block', 'Crane lifting with workers at height below the load path'],
        ['LF',  'EX',   'warn',  'Crane lifting near open excavation'],
        ['LF',  'MEWP', 'warn',  'Crane lifting and MEWP in one area'],
        ['LF',  'SL',   'warn',  'Crane lifting and scissor lift in one area'],
        ['EX',  'EL',   'block', 'Excavation near electrical work / live cables'],
        ['EX',  'PL',   'warn',  'Excavation near piling'],
        ['PT',  'CSE',  'block', 'Pressure testing near confined space entry'],
        ['PT',  'WAH',  'warn',  'Pressure testing beneath working at height'],
        ['HT',  'EL',   'warn',  'Hydrostatic testing near electrical work'],
        ['PL',  'WAH',  'warn',  'Piling vibration near working at height'],
        ['CSE', 'EL',   'warn',  'Confined space entry near electrical work'],
    ];
}
