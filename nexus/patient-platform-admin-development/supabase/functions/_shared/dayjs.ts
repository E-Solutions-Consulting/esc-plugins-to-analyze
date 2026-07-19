type DateTimeInput = string | number | Date | null | undefined;

type DateTimeValue = {
  toISOString: () => string;
  toDate: () => Date;
  valueOf: () => number;
};

type DateTimeFactory = {
  (input?: DateTimeInput): DateTimeValue;
  unix: (seconds: number) => DateTimeValue;
};

const createDateTimeValue = (date: Date): DateTimeValue => ({
  toISOString: () => date.toISOString(),
  toDate: () => new Date(date.getTime()),
  valueOf: () => date.valueOf(),
});

export const dateTime: DateTimeFactory = ((input?: DateTimeInput) => {
  const date = input instanceof Date
    ? new Date(input.getTime())
    : input === undefined || input === null
    ? new Date()
    : new Date(input);

  return createDateTimeValue(date);
}) as DateTimeFactory;

dateTime.unix = (seconds: number) =>
  createDateTimeValue(new Date(seconds * 1000));
