import { assertEquals } from "../_test/assert.ts";
import { type JotformSubmissionContent } from "./jotform.ts";
import {
  buildTelegraPatientDataFromJotformSubmission,
  parseHeightToInches,
  parseWeightToPounds,
} from "./telegra.ts";

Deno.test("buildTelegraPatientDataFromJotformSubmission maps patient questionnaire answers", () => {
  const submission: JotformSubmissionContent = {
    id: "submission-123",
    form_id: "261405426738055",
    answers: {
      "1": {
        name: "symptoms",
        text: "Tell us about your symptoms",
        type: "control_textarea",
        answer: "headache, nausea",
        order: "1",
      },
      "2": {
        name: "other_symptoms",
        text: "Other symptoms",
        type: "control_textarea",
        answer: "fatigue",
        order: "2",
      },
      "3": {
        name: "medication",
        text: "Medications",
        type: "control_textarea",
        answer: [
          {
            medication_name: "Atorvastatin",
            dosage: "10mg",
            frequency: "daily",
            condition_treated: "cholesterol",
          },
        ],
        order: "3",
      },
      "4": {
        name: "medication_confirmation",
        text: "Medication confirmation",
        type: "control_radio",
        answer: "Yes, Confirm",
        order: "4",
      },
      "5": {
        name: "allergies",
        text: "Allergies",
        type: "control_textarea",
        answer: "Penicillin",
        order: "5",
      },
      "6": {
        name: "allergies_confirmation",
        text: "Allergies confirmation",
        type: "control_radio",
        answer: "Yes",
        order: "6",
      },
      "7": {
        name: "biological_gender",
        text: "Biological gender",
        type: "control_radio",
        answer: "Female",
        order: "7",
      },
      "8": {
        name: "weight_lbs",
        text: "Weight",
        type: "control_textbox",
        answer: "145 lbs",
        order: "8",
      },
      "9": {
        name: "height_ft",
        text: "Height",
        type: "control_textbox",
        answer: "5 ft 6 in",
        order: "9",
      },
      "10": {
        name: "birth_date",
        text: "Birth date",
        type: "control_datetime",
        answer: { year: "1980", month: "2", day: "3" },
        order: "10",
      },
    },
  };

  assertEquals(buildTelegraPatientDataFromJotformSubmission(submission), {
    symptoms: ["headache", "nausea"],
    other_symptoms: "fatigue",
    medication: [
      {
        medication_name: "Atorvastatin",
        dosage: "10mg",
        frequency: "daily",
        Condition_threated: "cholesterol",
      },
    ],
    medication_confirmation: "Yes",
    allergies: [{ Medication: "Penicillin" }],
    allergies_confirmation: "Yes",
    biological_gender: "female",
    weight_lbs: "145 lbs",
    height_ft: "5 ft 6 in",
    birth_date: "1980-02-03",
  });
});

Deno.test("buildTelegraPatientDataFromJotformSubmission maps generated Jotform patient questionnaire answers", () => {
  const submission: JotformSubmissionContent = {
    id: "submission-456",
    form_id: "65761114173226",
    answers: {
      "5": {
        name: "symptoms",
        text: "Please check all the symptoms you have.",
        type: "control_checkbox",
        answer: ["Skin Rashes", "Gallstones", "Shortness Of Breath"],
        order: "5",
      },
      "14": {
        name: "medication",
        text: "Medications",
        type: "control_widget",
        answer:
          '[{"Medication name":"Ben-u-ron","Dosage":"1mg","Frequency":"3x/day","Condition treated":"Pain"}]',
        order: "14",
      },
      "21": {
        name: "allergies",
        text: "Medications",
        type: "control_widget",
        answer: '[{"Medication":"Ben-u-ron","Reaction":"Pain Relief"}]',
        order: "21",
      },
      "28": {
        name: "weight_unit",
        text: "Weight units",
        type: "control_dropdown",
        answer: "Kgs",
        order: "28",
      },
      "32": {
        name: "weight_value",
        text: "What is your current weight?",
        type: "control_number",
        answer: "77",
        order: "32",
      },
      "34": {
        name: "height_value",
        text: "What is your current height?",
        type: "control_number",
        answer: "175",
        order: "34",
      },
      "35": {
        name: "height_unit",
        text: "Height units",
        type: "control_dropdown",
        answer: "cm",
        order: "35",
      },
      "38": {
        name: "date_of_birth",
        text: "What's your birth date?",
        type: "control_datetime",
        answer: { day: "24", month: "06", year: "2000" },
        order: "38",
      },
      "50": {
        name: "other_symptoms",
        text: "Other symptoms not listed in previous screen",
        type: "control_textarea",
        answer: "None",
        order: "50",
      },
    },
  };

  assertEquals(buildTelegraPatientDataFromJotformSubmission(submission), {
    symptoms: ["Skin Rashes", "Gallstones", "Shortness Of Breath"],
    other_symptoms: "None",
    medication: [
      {
        medication_name: "Ben-u-ron",
        dosage: "1mg",
        frequency: "3x/day",
        Condition_threated: "Pain",
      },
    ],
    allergies: [
      {
        Medication: "Ben-u-ron",
        Reaction: "Pain Relief",
      },
    ],
    weight_lbs: "77 Kgs",
    height_ft: "175 cm",
    birth_date: "2000-06-24",
  });
});

Deno.test("buildTelegraPatientDataFromJotformSubmission maps generated imperial measurements", () => {
  const submission: JotformSubmissionContent = {
    id: "submission-789",
    form_id: "65761114173226",
    answers: {
      "28": {
        name: "weight_unit",
        text: "Weight units",
        type: "control_dropdown",
        answer: "lbs",
        order: "28",
      },
      "32": {
        name: "weight_value",
        text: "What is your current weight?",
        type: "control_number",
        answer: "154",
        order: "32",
      },
      "34": {
        name: "height_value",
        text: "What is your current height?",
        type: "control_number",
        answer: "5.8",
        order: "34",
      },
      "35": {
        name: "height_unit",
        text: "Height units",
        type: "control_dropdown",
        answer: "ft",
        order: "35",
      },
    },
  };

  assertEquals(buildTelegraPatientDataFromJotformSubmission(submission), {
    weight_lbs: "154 lbs",
    height_ft: "5.8 ft",
  });
});

Deno.test("Telegra patient questionnaire measurement parsing converts to pounds and inches", () => {
  assertEquals(parseWeightToPounds("77 Kgs"), 169.76);
  assertEquals(parseWeightToPounds("154 lbs"), 154);
  assertEquals(parseHeightToInches("175 cm"), 68.9);
  assertEquals(parseHeightToInches("5.8 ft"), 69.6);
  assertEquals(parseHeightToInches("5 ft 8 in"), 68);
});
