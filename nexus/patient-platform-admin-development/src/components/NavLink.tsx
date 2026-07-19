import { getNodeText, useAutoTestId } from "@/lib/accessibility";
import { cn } from "@/lib/utils";
import { forwardRef } from "react";
import { NavLinkProps, NavLink as RouterNavLink } from "react-router-dom";

interface NavLinkCompatProps extends Omit<NavLinkProps, "className"> {
  className?: string;
  activeClassName?: string;
  pendingClassName?: string;
}

const NavLink = forwardRef<HTMLAnchorElement, NavLinkCompatProps>(
  ({ className, activeClassName, pendingClassName, to, ...props }, ref) => {
    const childrenText =
      typeof props.children === "function"
        ? undefined
        : getNodeText(props.children);
    const testId = useAutoTestId("nav-link", props, childrenText);

    return (
      <RouterNavLink
        ref={ref}
        to={to}
        data-testid={testId}
        className={({ isActive, isPending }) =>
          cn(
            className,
            isActive && activeClassName,
            isPending && pendingClassName,
          )
        }
        {...props}
      />
    );
  },
);

NavLink.displayName = "NavLink";

export { NavLink };
